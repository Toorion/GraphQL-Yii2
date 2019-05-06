<?php
declare(strict_types=1);

namespace YiiGraphQL\Info;

use yii\db\Query;
use yii\helpers\StringHelper;
use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Type\YiiType;
use YiiGraphQL\Type\Definition\ListOfType;

class ListInfo extends ObjectInfo
{
    /*
     *
     * Configurable parameters
     *
     */

    /**
     * @var callable - list resolve function
     */
    public $resolveAny;


    /**
     * @var array - Array of args
     */
    public $argsAny = [];

    /**
     * @var array - array of expanded args for a List
     */
    public $expandArgsAny = [];


    public function getType()
    {
        return new ListOfType(parent::getType());
    }



    public function getResolveFn()
    {
        if(is_callable($this->resolveAny)) {
            return $this->resolveAny;
        }
    }


    public function getArgs()
    {
        return self::_assignArgs($this->argsAny, $this->expandArgsAny);
    }

}