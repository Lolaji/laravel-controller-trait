<?php

namespace Lolaji\LaravelControllerTrait\Helpers;

class Str {
    public static function isCommerSeparatedNumbers ($str="") {
        // Regular expression for comma-separated numbers
        $partern = '/^\d+(,\d+)*$/';

        // Return true if the string matches the pattern, false otherwise
        return preg_match($partern, $str);
    }
}