<?php
declare(strict_types=1);

namespace YiiGraphQL;

use yii\db\Query;
use YiiGraphQL\Type\Definition\Type;

class Info
{
    const EXPAND = 'expand';

    const ANY = 'any';

    const ARGS = 'args';

    const RESOLVE = 'resolve';

    const QUERY = 'query';

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
                $query = call_user_func($className . '::find');
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
                    $args[$name] = $this->queryModel->typeGraphQL($type);
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
                    $args[$name] = $this->queryModel->typeGraphQL($type);
                }
            }
        }

        return $args;
    }


}