<?php

// Remove this when mb_overload is no longer available for usage in PHP
if (!function_exists('mb_orig_substr')) {
    function mb_orig_substr($str, $start, $length = null)
    {
        return is_null($length) ? substr($str, $start) : substr($str, $start, $length);
    }

    function mb_orig_stripos($haystack, $needle, $offset = 0)
    {
        return stripos($haystack, $needle, $offset);
    }

    function mb_orig_strpos($haystack, $needle, $offset = 0)
    {
        return strpos($haystack, $needle, $offset);
    }

    function mb_orig_strlen($string)
    {
        return strlen($string);
    }
}
