<?php
declare(strict_types=1);

namespace YiiGraphQL;


use YiiGraphQL\Info\ListInfo;

abstract class ListModel extends ObjectModel
{

    public static function getObjectInfo(array $classConfig)
    {
        return new ListInfo(array_merge([
            'class' => static::class,
            'resolveAny' => [static::class, 'resolve'],
            'argsAny' => static::args(),
        ], $classConfig));
    }

}