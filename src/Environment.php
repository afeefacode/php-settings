<?php

namespace Afeefa\Component\Settings;

class Environment
{
    public const DEFAULT_ENV_KEY = 'AFEEFA_ENV';

    public const DEVELOPMENT = 'DEVELOPMENT';
    public const TEST = 'TEST';
    public const PRODUCTION = 'PRODUCTION';

    public static function isValid(?string $env): bool
    {
        return preg_match('/^DEVELOPMENT|TEST|PRODUCTION$/', $env);
    }
}
