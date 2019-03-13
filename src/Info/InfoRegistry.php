<?php
declare(strict_types=1);

namespace YiiGraphQL\Info;

class InfoRegistry
{
    protected static $objectRegistry = [];

    protected static $listRegistry = [];

    public static function getInfo(array $classConfig, $multiple = false)
    {
        if ($multiple) {
            if (isset(self::$listRegistry[$classConfig['class']])) {
                return self::$listRegistry[$classConfig['class']];
            }
            return self::$listRegistry[$classConfig['class']] = new ListInfo($classConfig);
        }

        if (isset(self::$objectRegistry[$classConfig['class']])) {
            return self::$objectRegistry[$classConfig['class']];
        }
        return self::$objectRegistry[$classConfig['class']] = new ObjectInfo($classConfig);
    }


}