<?php
declare(strict_types=1);

namespace YiiGraphQL\Info;

use yii\base\BaseObject;
use yii\helpers\StringHelper;
use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Type\YiiType;

class ObjectInfo
{

    /** @var array Cache of objectTypes */
    protected static $objectTypes = [];


    /*
     *
     * Configurable parameters
     *
     */

    /**
     * @var string - Yii2 Model
     */
    public $class;

    /**
     * @var callable - record resolve function
     */
    public $resolve;

    /**
     * @var  array - array of query arguments
     */
    public $args = [];

    /**
     * @var array - array of expanded args
     */
    public $expandArgs = [];


    public function __construct(array $config = [])
    {
        foreach ($config as $name => $value) {
            if(property_exists($this, $name)) {
                $this->$name = $value;
            }
        }
    }


    public function getType()
    {
        if (isset(self::$objectTypes[$this->class])) {
            return self::$objectTypes[$this->class];
        }

        return self::$objectTypes[$this->class] = new ObjectType([
            'name' => StringHelper::basename($this->class),
            'description' => "Class [$this->class]"
        ]);
    }


    public function getResolveFn()
    {
        if (is_callable($this->resolve)) {
            return $this->resolve;
        }
    }


    public function getArgs()
    {
        $args = [];
        if (count($this->args) > 0) {
            $args = [];
            foreach ($this->args as $name => $type) {
                if( null === ($graphType = YiiType::cast($type))) {
                    continue;
                }
                $args[$name] = $graphType;
            }
        }

        foreach ($this->expandArgs as $name => $type) {
            if( null === ($graphType = YiiType::cast($type))) {
                continue;
            }
            $args[$name] = $graphType;
        }

        return $args;
    }

}