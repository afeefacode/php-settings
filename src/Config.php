<?php

namespace Afeefa\Component\Settings;

use Afeefa\Component\Settings\Test\ArrayUtils;
use Traversable;

class Config implements \ArrayAccess, \IteratorAggregate
{
    protected ?Config $root;
    protected ?Config $parent;
    protected array $config = [];
    protected array $cache = [];

    public static function cast(array $values): array
    {
        if (static::class !== Config::class) {
            $values['__class'] = static::class;
        }
        return $values;
    }

    public static function extend(array $values): array
    {
        $config = new static($values);
        $values = $config->serialize();
        unset($values['__class']);
        return $values;
    }

    public static function delegate(string $key): array
    {
        return [
            '__class' => ConfigDelegate::class,
            '__delegate' => $key
        ];
    }

    public function __construct(array $config, Config $root = null, Config $parent = null)
    {
        $this->root = $root ?: $this;
        $this->parent = $parent;

        foreach ($config as $key => $value) {
            $this->setValue($key, $value);
        }
    }

    public function get(string $key, $default = '___DEFAULT____', string $rootKey = '')
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        if (!$rootKey) {
            $rootKey = $key;
        }

        if (preg_match("/\./", $key)) {
            $keys = explode('.', $key);
            $last = array_pop($keys);
            $parentConfig = $this->get(implode('.', $keys), $default, $rootKey);

            if (!$parentConfig instanceof Config) {
                if ($default !== '___DEFAULT____') {
                    return $default;
                }
                throw new NotFoundException(sprintf('Identifier "%s" is not defined.', $rootKey));
            }

            $value = $parentConfig->get($last, $default, $rootKey);
            $this->cache[$key] = $value;
            return $value;
        }

        if (array_key_exists($key, $this->config)) {
            $value = $this->config[$key];
            if ($value instanceof Config) {
                $value = $value->extractValue();
            }
            $this->cache[$key] = $value;
            return $value;
        }

        if ($default !== '___DEFAULT____') {
            return $default;
        }

        throw new NotFoundException(sprintf('Identifier "%s" is not defined.', $rootKey));
    }

    /**
     * only for testing purposes
     */
    public function raw(string $key)
    {
        if (preg_match("/\./", $key)) {
            $keys = explode('.', $key);
            $last = array_pop($keys);
            $parentConfig = $this->raw(implode('.', $keys));
            return $parentConfig->raw($last);
        }
        return $this->config[$key];
    }

    public function has(string $key, bool $notEmpty = false): bool
    {
        if (array_key_exists($key, $this->cache)) {
            return $notEmpty ? !!$this->cache[$key] : true;
        }

        if (preg_match("/\./", $key)) {
            $keys = explode('.', $key);
            $last = array_pop($keys);
            $parentConfig = $this->get(implode('.', $keys), true); // set default = true to prevent not found exception
            if (!$parentConfig instanceof Config) {
                return false;
            }
            return $parentConfig->has($last, $notEmpty);
        }

        if (array_key_exists($key, $this->config)) {
            $value = $this->config[$key];
            if ($value instanceof Config) {
                $value = $value->extractValue();
            }
            $this->cache[$key] = $value;
            return $notEmpty ? !!$value : true;
        }

        return false;
    }

    public function hasCached(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }

    public function set(string $key, $value, string $rootKey = ''): void
    {
        if (!$key && (string) $key !== '0') {
            return;
        }

        if (!$rootKey) {
            $rootKey = $key;
        }

        if (preg_match("/\./", $key)) {
            $keys = explode('.', $key);
            $last = array_pop($keys);
            $parentConfig = $this->get(implode('.', $keys), true); // set default = true to prevent not found exception

            if (!$parentConfig instanceof Config) {
                throw new NotFoundException(sprintf('Identifier "%s" is not a config object.', implode('.', $keys)));
            }

            $parentConfig->set($last, $value, $rootKey);
            return;
        }
        $this->setValue($key, $value);
        $this->purgeCache();
    }

    public function remove(string $key): void
    {
        if (preg_match("/\./", $key)) {
            $keys = explode('.', $key);
            $last = array_pop($keys);
            $parentConfig = $this->get(implode('.', $keys));
            $parentConfig->remove($last);
            return;
        }

        if (!$this->offsetExists($key)) {
            throw new NotFoundException(sprintf('Identifier "%s" is not defined.', $key));
        }

        unset($this->config[$key]);
        $this->purgeCache();
    }

    public function merge(array $config): void
    {
        foreach ($config as $key => $value) {
            // do not allow overriding config class
            if ($key === '__class') {
                continue;
            }

            if (array_key_exists($key, $this->config)) {
                $existingValue = $this->config[$key];
                // merge assoc config but not if a delegate is given, which shall be replaced
                $replaceDelegate = is_array($value) && array_key_exists('__delegate', $value);
                if ($existingValue instanceof Config && !$replaceDelegate) {
                    if (ArrayUtils::isAssoc($value)) {
                        $existingValue->merge($value);
                        continue;
                    }
                }
            }

            $this->setValue($key, $value);
        }
        $this->purgeCache();
    }

    public function toArray(): array
    {
        $array = [];
        foreach ($this->config as $key => $value) {
            if ($value instanceof Config) {
                $value = $value->extractValue();
            }

            if ($value instanceof Config) {
                $array[$key] = $value->toArray();
            } else {
                $array[$key] = $value;
            }
        }
        return $array;
    }

    public function serialize(): array
    {
        $array = [];
        if (static::class !== Config::class) {
            $array['__class'] = static::class;
        }
        foreach ($this->config as $key => $value) {
            if ($value instanceof Config) {
                $array[$key] = $value->serialize();
            } else {
                $array[$key] = $value;
            }
        }
        return $array;
    }

    public function visit(ConfigVisitor $visitor, $parent = null, $parentKey = null, $rootKey = null): void
    {
        $visitor->visit($parent, $parentKey ?? '', $rootKey ?? '', $this);
        foreach ($this->config as $key => $value) {
            if ($value instanceof Config && !$value instanceof ConfigDelegate) {
                $currentRootKeyKey = $rootKey ? ($rootKey . '.' . $key) : $key;
                $value->visit($visitor, $this, $key, $currentRootKeyKey);
            }
        }
    }

    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    public function offsetGet($offset, $default = null)
    {
        return $this->get($offset, $default);
    }

    public function offsetSet($offset, $value): void
    {
        $this->set($offset, $value);
    }

    public function getIterator(): Traversable
    {
        $array = [];
        foreach ($this->config as $key => $value) {
            if ($value instanceof Config) {
                $value = $value->extractValue();
            }
            $array[$key] = $value;
        }
        return new \ArrayIterator($array);
    }

    /**
     * Returns the config itself and allows ConfigDelegate to
     * return a delegated value.
     */
    protected function extractValue()
    {
        return $this;
    }

    private function setValue(string $key, $value): void
    {
        if (!$this->valueIsAllowed($value)) {
            throw new IllegalValueException(sprintf('Value for "%s" is not allowed: ' . $this->getValueInfo($value) . '.', $key));
        }

        if ($value instanceof Config) { // if setting a Config directly, we want to purge it's cache and all it's parent caches
            $value->setRoot($this->root);
            $value->parent = $this;
            $value->purgeCache();
            $this->config[$key] = $value;
            return;
        }

        $isList = false;
        if (preg_match("/\[\]$/", $key)) {
            $isList = true;
            $key = substr($key, 0, -2);
        }

        if ($isList) {
            // append to existing array
            if ($this->currentValueIsNumericArray($key)) {
                $appends = is_array($value) ? $value : [$value];
                foreach ($appends as $append) {
                    if (is_array($append)) { // make config from array
                        $this->config[$key]->config[] = $this->createConfigFromArray($key, $append);
                    } else { // directly assign
                        $this->config[$key]->config[] = $append;
                    }
                }
                return;
            }

            // create an array otherwise
            if (!is_array($value)) {
                $value = [$value];
            }
        }

        if (is_array($value)) { // make config from array
            $this->config[$key] = $this->createConfigFromArray($key, $value);
        } else { // directly assign
            $this->config[$key] = $value;
        }
    }

    private function setRoot(Config $root)
    {
        $this->root = $root;
        foreach ($this->config as $value) {
            if ($value instanceof Config) {
                $value = $value->extractValue();
                $value->setRoot($root);
            }
        }
    }

    private function createConfigFromArray(string $key, array $array): Config
    {
        $Class = Config::class;
        if (isset($array['__class'])) {
            $Class = $array['__class'];
            if (!class_exists($Class)) {
                throw new IllegalValueException(sprintf('Class for "%s" does not exist: ' . $Class . '.', $key));
            }
            unset($array['__class']);
        }

        return new $Class($array, $this->root, $this);
    }

    private function currentValueIsNumericArray(string $key): bool
    {
        $value = $this->config[$key] ?? null;

        if (!$value instanceof Config) {
            return false;
        }

        if (ArrayUtils::isAssoc($this->config[$key]->config)) {
            return false;
        }

        return true;
    }

    private function valueIsAllowed($value): bool
    {
        if ($value instanceof Config) { // allow setting a Config object directly
            return true;
        }

        if (is_string($value)) {
            return true;
        }

        if (is_bool($value)) {
            return true;
        }

        if (!$value) {
            return true;
        }

        if (is_numeric($value)) {
            return true;
        }

        if (is_array($value)) {
            return true;
        }

        return false;
    }

    private function getValueInfo($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }
        return $value;
    }

    private function purgeCache(): void
    {
        $this->cache = [];
        if ($this->parent) {
            $this->parent->purgeCache();
        }
    }
}
