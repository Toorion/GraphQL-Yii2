<?php
declare(strict_types=1);

namespace YiiGraphQL;

use yii\base\Model;
use YiiGraphQL\Info\ObjectInfo;
use YiiGraphQL\Type\Definition\ResolveInfo;

abstract class ObjectModel extends Model
{

    public static function getObjectInfo(array $classConfig)
    {
        return new ObjectInfo(array_merge([
            'class' => static::class,
            'resolve' => [static::class, 'resolve'],
            'args' => static::args(),
        ], $classConfig));
    }


    /*
     * Model Resolve Function
     */
    abstract public static function resolve($root, $args, $context, $info);

    /*
     * Description of Model Arguments
     */
    abstract public static function args();


    abstract public static function objectFields();

    public function getRequestFields(ResolveInfo $info) {
        $fieldNode = $info->fieldNodes[0];
        $selectionSet = $fieldNode->selectionSet;
        $selections = $selectionSet->selections;

        $fields = [];
        foreach($selections as $node) {
            $fields[] = $node->name->value;
        }

        return $fields;
    }

}