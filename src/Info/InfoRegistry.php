<?php
declare(strict_types=1);

namespace YiiGraphQL\Info;

use yii\db\ActiveRecord;
use YiiGraphQL\ListModel;
use YiiGraphQL\ObjectModel;

class InfoRegistry
{
    protected static $objectRegistry = [];

    protected static $listRegistry = [];

    public static function getInfo(array $classConfig, $multiple = false)
    {
//        if(is_a($classConfig['class'], ActiveRecord::class, true)) {
            if ($multiple) {
                if (isset(self::$listRegistry[$classConfig['class']])) {
                    return self::$listRegistry[$classConfig['class']];
                }
                return self::$listRegistry[$classConfig['class']] = new RecordsInfo($classConfig);
            }

            if (isset(self::$objectRegistry[$classConfig['class']])) {
                return self::$objectRegistry[$classConfig['class']];
            }
            return self::$objectRegistry[$classConfig['class']] = new RecordInfo($classConfig);
//        }

//        if(is_a($classConfig['class'], ListModel::class, true)) {
//            $className = $classConfig['class'];
//            return self::$objectRegistry[$classConfig['class']] = $className::getObjectInfo($classConfig);
//        } elseif(is_a($classConfig['class'], ObjectModel::class, true)) {
//            $className = $classConfig['class'];
//            return self::$objectRegistry[$classConfig['class']] = $className::getObjectInfo($classConfig);
//        }
    }


}