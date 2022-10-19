<?php

namespace Afeefa\Component\Settings;

use Closure;

class ConfigVisitor
{
    private Closure $callback;
    private $visitedKeys = [];

    public function __construct(Closure $callback)
    {
        $this->callback = $callback;
    }

    public function visit(?Config $parent, string $parentKey, string $rootKey, Config $config): void
    {
        if (isset($this->visitedKeys[$rootKey])) {
            return;
        }
        $callback = $this->callback;
        $callback($parent, $parentKey, $rootKey, $config);
        $this->visitedKeys[$rootKey] = true;
    }

    public function getVisitedKeys(): array
    {
        return array_keys($this->visitedKeys);
    }
}
