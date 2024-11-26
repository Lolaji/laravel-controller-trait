<?php

namespace Lolaji\LaravelControllerTrait\Helpers;

class Arr 
{
    public static function isMulti (array $array)
    {
        $inner = array_filter($array,'is_array');
        if(count($inner)>0) return true;
        return false;
    }

    public static function hasEmptyMulti (array $array)
    {
        $inner = array_filter($array, function ($inner) {
            return empty($inner);
        });
        if (count($inner) > 0) return true;
        return false;
    }
}