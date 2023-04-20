<?php

namespace Knuckles\Faktory\Utils;

class Json
{
    public static function parse(?string $string)
    {
        return json_decode($string, true, JSON_THROW_ON_ERROR);
    }

    public static function stringify(mixed $item)
    {
        return json_encode($item, JSON_THROW_ON_ERROR);
    }
}
