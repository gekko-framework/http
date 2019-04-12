<?php
/*
 * (c) Leonardo Brugnara
 *
 * Full copyright and license information in LICENSE file.
 */

namespace Gekko\Http;

class RequestParameter
{
    public static function newInstance($arguments)
    {
        $class = get_called_class();
        $instance = new $class;
        foreach ($arguments as $prop => $value) {
            if (property_exists($instance, $prop)) {
                $instance->{$prop} = $value;
            }
        }
        return $instance;
    }

    public static function tryNewInstanceOf($class, $arguments)
    {
        switch ($class) {
            case 'DateTime':
                return new \DateTime($arguments);
            default:
                return null;
        }
    }
}
