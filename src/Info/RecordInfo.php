<?php
declare(strict_types=1);

namespace YiiGraphQL\Info;

use yii\base\BaseObject;
use yii\helpers\StringHelper;
use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Type\YiiType;

class RecordInfo extends ObjectInfo
{

    public function getResolveFn()
    {
        if (is_callable($this->resolve)) {
            return $this->resolve;
        }

        return function ($root, $args) {
            // Expand find one unintelligible
            return call_user_func($this->class . '::findOne', $args['id']);
        };
    }


    public function getArgs()
    {
        $args = [
            'id' => Type::nonNull(Type::int()),
        ];

        return array_merge($args, parent::getArgs());
    }

}