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
     * @var string - Name of SearchQuery class
     */
    public $searchClass;

    /**
     * @var callable - Expand query function
     * Example
     * function($query, $args) {
     *     if(isset($args['arg_name'])) {
     *         $query->andWhere(['field_name' => $args['arg_name']]);
     *     }
     * }
     */
    public $query;


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

            return function ($root, $args) {

                $xPagination = isset($args['x_pagination']) && $args['x_pagination'] === true;

                /** @var Query $query */
                if(null !== $this->searchClass) {
                    $searchModel = new $this->searchClass();
                    $query = $searchModel->search([StringHelper::basename($this->searchClass) => $args])->query;
                } else {
                    $query = call_user_func($this->class . '::find');
                }

                if (isset($args['filter'])) {
                    $query->andWhere($args['filter']);
                }

                if($xPagination) {
                    $count = $query->count();
                    $limit = $count;
                    $offset = 0;
                }

                if (isset($args['limit'])) {
                    $query->limit($args['limit']);
                    $limit = $args['limit'];
                }
                if (isset($args['offset'])) {
                    $query->offset($args['offset']);
                    $offset = $args['offset'];
                }
                if (isset($args['sort'])) {
                    $query->orderBy($args['sort']);
                }

                if(is_callable($this->query)) {
                    ${$this->query}($query, $args);
                }

                if($xPagination) {
                    header("X-Pagination-Total-Count: " . $count);
                    header("X-Pagination-Page-Count: " . ceil($count / $limit));
                    header("X-Pagination-Current-Page: " . ceil($offset / $limit));
                    header("X-Pagination-Per-Page: " . $limit);
                }

                return $query->all();
            };
    }


    public function getArgs()
    {
        if (count($this->argsAny) > 0) {
            $args = [];
            foreach ($this->argsAny as $name => $type) {
                if( null === ($graphType = YiiType::cast($type))) {
                    continue;
                }
                $args[$name] = $graphType;
            }
            return $args;
        }

        $xPagination = Type::boolean();
        $xPagination->config['defaultValue'] = 'false';

        $args = [
            'limit' => Type::int(),
            'offset' => Type::int(),
            'sort' => Type::string(),
            'filter' => Type::string(),
            'x_pagination' => $xPagination,
        ];

        foreach($this->expandArgsAny as $name => $type) {
            $graphType =  YiiType::cast($type);
            if(null !== $graphType) {
                $args[$name] = $graphType;
            }
        }

        if(isset($this->searchClass)) {
            foreach ($this->argsBySearchModel() as $name => $type) {
                if( null === ($graphType = YiiType::cast($type))) {
                    continue;
                }
                $args[$name] = $graphType;
            }
        }

        return $args;
    }



    protected function argsBySearchModel()
    {
        $args = [];

        $reflectionClass = new \ReflectionClass($this->searchClass);
        $properties = $reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach($properties as $property) {
            if($property->class == $this->searchClass) {
                $args[$property->name] = 'string';
            }
        }

        if(method_exists($this->searchClass, 'rules')) {

            $model = new $this->searchClass();
            $rules = $model->rules();

            foreach($rules as $rule) {
                $type = $rule[1];
                if(null === $type)
                    continue;

                if(is_array($rule[0])) {
                    foreach($rule[0] as $varName) {
                        $args[$varName] = $type;
                    }
                } else {
                    $args[$rule[0]] = $type;
                }
            }
        }
        return $args;
    }
}