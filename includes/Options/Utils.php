<?php

namespace RRZE\Calendar\Options;

defined('ABSPATH') || exit;

class Utils
{
    /**
     * Mask secure values.
     *
     * @param string $value Original value.
     * @param string $hint  Number of characters to show.
     * 
     * @return string
     */
    public static function maskSecureValues($value, $hint = 6)
    {
        $count = strlen($value);
        if ($count > 0 && $count <= $hint) {
            $value = str_pad($value, $count, '*', STR_PAD_LEFT);
        } elseif ($count > $hint) {
            $substr = substr($value, -$hint);
            $value = str_pad($substr, $count, '*', STR_PAD_LEFT);
        }
        return $value;
    }
}
