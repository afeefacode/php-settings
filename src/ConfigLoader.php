<?php

namespace Afeefa\Component\Settings;

use Symfony\Component\Filesystem\Path;

class ConfigLoader
{
    private array $paths = [];

    public function addPaths()
    {
        $paths = func_get_args();
        foreach ($paths as $path) {
            $this->paths[] = $path;
        }
        return $this;
    }

    public function path(string $path): ConfigLoaderPath
    {
        return new ConfigLoaderPath($path);
    }

    public function load(string $env): Config
    {
        $config = new Config([]);

        foreach ($this->paths as $path) {
            $settingDirs = glob($path->path);
            foreach ($settingDirs as $settingDir) {
                foreach ($path->files as $file) {
                    if ($file === '$ENV') {
                        $file = strtolower($env);
                    }
                    $configFile = Path::join($settingDir, $file . '.php');
                    if (file_exists($configFile)) {
                        $currentConfig = $this->loadConfig($configFile);

                        $currentConfig = $this->transformPathsToArrays($currentConfig);

                        $config->merge($currentConfig);
                    }
                }
            }
        }

        return $config;
    }

    /**
     * a.b.c => [a => [b => [c]]]
     */
    private function transformPathsToArrays(array $array): array
    {
        $transformed = [];
        foreach ($array as $key => $value) {
            $entry = [];
            $this->transformEntryToArray($entry, $key, $value);
            $transformed = array_replace_recursive($transformed, $entry);
        }
        return $transformed;
    }

    private function transformEntryToArray(&$entry, $path, $value)
    {
        $keys = explode('.', $path);
        foreach ($keys as $key) {
            $entry = &$entry[$key];
        }
        if (is_array($value)) {
            $entry = $this->transformPathsToArrays($value);
        } else {
            $entry = $value;
        }
    }

    private function loadConfig($configFile)
    {
        return require $configFile;
    }
}

class ConfigLoaderPath
{
    public $path;
    public $files = [];

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function files()
    {
        $this->files = func_get_args();
        return $this;
    }
}
