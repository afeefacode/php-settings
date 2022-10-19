<?php

namespace Afeefa\Component\Settings;

class ConfigDelegate extends Config
{
    private string $rootKey = '';

    public function __construct(array $config, Config $root = null, Config $parent = null)
    {
        $this->rootKey = $config['__delegate'];
        parent::__construct([], $root, $parent);
    }

    public function serialize(): array
    {
        return [
            '__class' => ConfigDelegate::class,
            '__delegate' => $this->rootKey
        ];
    }

    public function set(string $key, $value, string $rootKey = ''): void
    {
        $key = $this->rootKey . '.' . $key;
        $this->root->set($key, $value);
    }

    public function get(string $key, $default = '___DEFAULT____', string $rootKey = '')
    {
        $delegate = $this->getDelegate();
        return $delegate->get($key, $default, $rootKey);
    }

    public function has(string $key, bool $notEmpty = false): bool
    {
        $delegate = $this->getDelegate();
        return $delegate->has($key, $notEmpty);
    }

    protected function extractValue()
    {
        return $this->getDelegate();
    }

    private function getDelegate()
    {
        return $this->root->get($this->rootKey);
    }
}
