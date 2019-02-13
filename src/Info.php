<?php
declare(strict_types=1);

namespace YiiGraphQL;

use yii\db\Query;
use yii\helpers\StringHelper;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Type\YiiType;

class Info
{
    const EXPAND = 'expand';

    const ANY = 'any';

    const ARGS = 'args';

    const RESOLVE = 'resolve';

    const QUERY = 'query';

    const SEARCH_CLASS = 'searchClass';

    /** @var QueryModel */
    protected $queryModel;

    protected $config;

    protected $multiple;

    public function __construct(QueryModel $queryModel, $config, $multiple)
    {
        $this->queryModel = $queryModel;
        $this->config = $config;
        $this->multiple = $multiple;
    }



    public function getType()
    {
        return $this->queryModel->getObjectType($this->config['class'], $this->multiple);
    }



    public function getResolveFn()
    {
        $className = $this->config['class'];

        if($this->multiple) {
            if(isset($this->config[self::ANY][self::RESOLVE]) && is_callable($this->config[self::ANY][self::RESOLVE])) {
                return $this->config[self::ANY][self::RESOLVE];
            }

            return function ($root, $args) use ($className) {

                /** @var Query $query */
                if(isset($this->config[self::SEARCH_CLASS])) {
                    $searchClass = $this->config[self::SEARCH_CLASS];
                    $searchModel = new $searchClass();
                    $query = $searchModel->search([StringHelper::basename($searchClass) => $args])->query;
                } else {
                    $query = call_user_func($className . '::find');
                }

                if (isset($args['limit'])) {
                    $query->limit($args['limit']);
                }
                if (isset($args['offset'])) {
                    $query->offset($args['offset']);
                }
                if (isset($args['sort'])) {
                    $query->orderBy($args['sort']);
                }
                if (isset($args['filter'])) {
                    $query->andWhere($args['filter']);
                }

                if(isset($this->config[self::EXPAND][self::ANY][self::QUERY])) {
                    $queryFn = $this->config[self::EXPAND][self::ANY][self::QUERY];
                    if(is_callable($queryFn)) {
                        $queryFn($query, $args);
                    }
                }

                return $query->all();
            };
        } else {
            if(isset($this->config['resolve']) && is_callable($this->config['resolve'])) {
                return $this->config['resolve'];
            }

            return function ($root, $args) use ($className) {

                // Expand find one unintelligible

                return call_user_func($className . '::findOne', $args['id']);
            };
        }

    }


    public function getArgs()
    {
        if($this->multiple) {
            if(isset($this->config['any']['args'])) {
                return $this->config['any']['args'];
            }

            $args = [
                'limit' => Type::int(),
                'offset' => Type::int(),
                'sort' => Type::string(),
                'filter' => Type::string(),
            ];

            if(isset($this->config[self::EXPAND][self::ANY][self::ARGS])) {
                foreach($this->config[self::EXPAND][self::ANY][self::ARGS] as $name => $type) {
                    $graphType =  YiiType::cast($type);
                    if(null !== $graphType) {
                        $args[$name] = $graphType;
                    }
                }
            }

            if(isset($this->config[self::SEARCH_CLASS])) {
                foreach ($this->argsBySearchModel() as $name => $type) {
                    $graphType =  YiiType::cast($type);
                    if(null !== $graphType) {
                        $args[$name] = $graphType;
                    }
                }
            }

        } else {
            if(isset($this->config[self::ARGS])) {
                return $this->config[self::ARGS];
            }

            $args = [
                'id' => Type::nonNull(Type::int()),
            ];

            if(isset($this->config[self::EXPAND][self::ARGS])) {
                foreach($this->config[self::EXPAND][self::ARGS] as $name => $type) {
                    $args[$name] = YiiType::cast($type);
                }
            }
        }

        return $args;
    }



    protected function argsBySearchModel()
    {
        $args = [];

        $className = $this->config[self::SEARCH_CLASS];

        $reflectionClass = new \ReflectionClass($className);
        $properties = $reflectionClass->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach($properties as $property) {
            if($property->class == $className) {
                $args[$property->name] = 'string';
            }
        }

        if(method_exists($this->config[self::SEARCH_CLASS], 'rules')) {

            $model = new $className();
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