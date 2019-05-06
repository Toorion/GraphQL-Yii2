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


    public $_argDescriptions = [];

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
        return $this->_assignArgs($this->args, $this->expandArgs);
    }


    protected function _assignArgs($_args, $expandArgs)
    {
        $args = [];
        if (count($_args) > 0) {
            $args = [];
            foreach ($_args as $name => $type) {
                if(is_array($type)) {
                    if (null === ($graphType = YiiType::cast($type['type']))) {
                        continue;
                    }
                    $this->_argDescriptions[$name] = $type['description'] ?? null;
                } else {
                    if (null === ($graphType = YiiType::cast($type))) {
                        continue;
                    }
                }
                $args[$name] = $graphType;
            }
        }

        foreach ($expandArgs as $name => $type) {
            if( null === ($graphType = YiiType::cast($type))) {
                continue;
            }
            $args[$name] = $graphType;
        }

        return $args;
    }
}