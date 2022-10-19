<?php

namespace Afeefa\Component\Settings;

class ConfigCache extends ConfigLoader
{
    private ?string $cacheFile;

    public function __construct(?string $cacheFile)
    {
        $this->cacheFile = $cacheFile;
    }

    public function load(string $env, callable $afterLoad = null): Config
    {
        if ($this->cacheFile && file_exists($this->cacheFile)) {
            $config = new Config(include $this->cacheFile);
        } else {
            $config = $this->loadFromFilesystem($env, $afterLoad);

            if ($afterLoad) {
                $afterLoad($config);
            }

            if ($this->cacheFile && !file_exists($this->cacheFile)) {
                $this->writeToFilesystem($config);
            }
        }

        return $config;
    }

    protected function loadFromFilesystem($env)
    {
        return parent::load($env);
    }

    protected function writeToFilesystem(Config $config)
    {
        $serialized = var_export($config->serialize(), true);
        $serialized = '<?php return ' . $serialized . ';';
        file_put_contents($this->cacheFile, $serialized);
    }

    public function purge()
    {
        if ($this->cacheFile && file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }
}
