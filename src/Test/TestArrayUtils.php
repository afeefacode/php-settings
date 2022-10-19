<?php

namespace Afeefa\Component\Settings\Test;

class TestArrayUtils
{
    public static function patchExpectedArray(&$a1, $a2, $callback)
    {
        foreach ($a1 as $a1Key => &$a1Value) {
            $a2Value = $a2[$a1Key] ?? null;
            if (is_array($a1Value)) {
                self::patchExpectedArray($a1Value, $a2Value, $callback);
            } else {
                $a1[$a1Key] = $callback($a1Key, $a1Value, $a2Value);
            }
        }
    }
}
