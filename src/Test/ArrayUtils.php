<?php

namespace Afeefa\Component\Settings\Test;

class ArrayUtils
{
    public static function isAssoc($array)
    {
        if (!is_array($array)) {
            return false;
        }

        if (!count($array)) {
            return false;
        }

        foreach ($array as $k => $v) {
            if (is_int($k)) {
                return false;
            }
        }

        return true;
    }
}
