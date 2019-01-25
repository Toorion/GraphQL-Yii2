<?php
declare(strict_types=1);

namespace YiiGraphQL;

use yii\base\Model;
use yii\helpers\StringHelper;
use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\ListOfType;
use yii\db\Query;
use yii\helpers\Inflector;
use YiiGraphQL\Type\Definition\Type;

class QueryModel extends Model
{

    /*
     * 'user' => [
     *   'class' => 'common\\models\\User',
     *   'resolve' => function($root, $args) {
     *           return User::find()->andWhere(['id' => $args['id']])->one();
     *   }
     * ]
     *
     */
    public $queryClasses = [];

    protected $tableSchemas = [];

    protected $foreignKeys;

    protected $relations = [];

    protected $types = [];

    public function __construct(array $queryClasses)
    {
        $this->queryClasses = $queryClasses;
        return parent::__construct();
    }


    public function getRelationsOf($tableSchema, $tableName)
    {
        $key = "{$tableSchema}.{$tableName}";
        if(!isset($this->relations[$key])) {

            $alias = $this->getTableAlias(str_replace('public.', '', $key));

            $this->relations[$key] = [];

            if(isset($this->queryClasses[$alias]) && isset($this->queryClasses[$alias]['relations'])) {
                foreach ($this->queryClasses[$alias]['relations'] as $relationName) {
                    $this->relations[$key][lcfirst($relationName)] = ['name' => ucfirst($relationName)];
                }
            }

            if(null === $this->foreignKeys) {
                $this->loadForeignKeys();
            }

            if(isset($this->foreignKeys[$key])) {
                $foreignKeys = $this->foreignKeys[$key];

                foreach ($foreignKeys as $row) {
                    if($row['table_schema'] == $tableSchema && $row['table_name'] == $tableName) {
                        $name = $row['foreign_table_name'];
                    } else {
                        $name = $row['table_name'];
                    }

                    $name = str_replace('_', '', ucwords($name, '_'));

                    $this->relations[$key][lcfirst($name)] = ['name' => $name];
                }
            }
        }

        return $this->relations[$key];
    }


    public function loadForeignKeys()
    {
        $query = \Yii::$app->db->createCommand("SELECT
              tc.table_schema,
              -- tc.constraint_name,
              tc.table_name,
              kcu.column_name,
              ccu.table_schema AS foreign_table_schema,
              ccu.table_name AS foreign_table_name,
              ccu.column_name AS foreign_column_name
            FROM
              information_schema.table_constraints AS tc
              JOIN information_schema.key_column_usage AS kcu
                ON tc.constraint_name = kcu.constraint_name
                   AND tc.table_schema = kcu.table_schema
              JOIN information_schema.constraint_column_usage AS ccu
                ON ccu.constraint_name = tc.constraint_name
                   AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY';");

        $this->foreignKeys = [];
        foreach($query->queryAll() as $row) {
            $key = $row['table_schema'] . '.' . $row['table_name'];
            if(!isset($this->foreignKeys[$key])) {
                $this->foreignKeys[$key] = [];
            }
            $this->foreignKeys[$key][] = $row;

            // Foreign
            $key = $row['foreign_table_schema'] . '.' . $row['foreign_table_name'];
            if(!isset($this->foreignKeys[$key])) {
                $this->foreignKeys[$key] = [];
            }
            $this->foreignKeys[$key][] = $row;
        }
    }



    public function getFieldType($model, $fieldName)
    {
        $tableName = $model->tableName();
        if(isset($this->tableSchemas[$tableName])) {
            $tableSchema = $this->tableSchemas[$tableName];
        } else {
            $tableSchema = $this->tableSchemas[$tableName] = $model::getTableSchema();
        }

        if(isset($tableSchema->columns[$fieldName])) {
            return $this->typeGraphQL($tableSchema->columns[$fieldName]->type);
        }

        return $this->typeGraphQL();
    }



    protected function typeGraphQL($stringType = 'string')
    {
        switch($stringType) {
            case "integer":
            case "int":
            case "smallint":
                return Type::int();
            case "string":
            case "safe":
            case "text":
            case "json":
                return Type::string();
            case "boolean":
                return Type::boolean();
            case "double":
            case "number":
                return Type::float();
            default:
                throw new \Exception("Type {$stringType} unknown");
        }
    }

    public function getObjectType($className, $multiple = false)
    {
        $alias = StringHelper::basename($className);

        if(isset($this->types[$alias])) {
            return $this->types[$alias];
        }

        $config = ['name' => $alias, 'description' => "Description of model [$alias]"];

        if($multiple) {
            $typeObject = new ListOfType(new ObjectType($config));
        } else {
            $typeObject = new ObjectType($config);
        }

        return $this->types[$alias] = $typeObject;
    }


    public function discoverObjectInfo($name)
    {
        $name = str_replace('_', '.', $name);

        $name = mb_strtolower(
            preg_replace( '/([a-z0-9])([A-Z])/', "$1_$2", $name )
        );

        $multiple = false;
        $alias = $name;
        if($name == Inflector::pluralize($name)) {
            $multiple = true;
            $alias = Inflector::singularize($name);
        }

        if(!isset($this->queryClasses[$alias])) {
            return null; //todo: throw an Exception
        }

        return [
            'type' => $this->getObjectType($this->queryClasses[$alias]['class'], $multiple),
            'resolve' => $this->getResolveFn($alias, $multiple),
            'args' => $this->getArgs($alias, $multiple),
        ];
    }




    public function getResolveFn($typeName, $multiple = false)
    {
        $className = $this->queryClasses[$typeName]['class'];

        if($multiple) {
            if(isset($this->queryClasses[$typeName]['resolve']) && is_callable($this->queryClasses[$typeName]['resolve'])) {
                return $this->queryClasses[$typeName]['resolve'];
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

                return $query->all();
            };
        } else {
            if(isset($this->queryClasses[$typeName]['resolveOne']) && is_callable($this->queryClasses[$typeName]['resolveOne'])) {
                return $this->queryClasses[$typeName]['resolveOne'];
            }

            return function ($root, $args) use ($className) {
                return call_user_func($className . '::findOne', $args['id']);
            };
        }
    }


    public function getArgs($typeName, $multiple)
    {
        if($multiple) {
            if(isset($this->queryClasses[$typeName]['args'])) {
                return $this->queryClasses[$typeName]['args'];
            }

            return [
                'limit' => Type::int(),
                'offset' => Type::int(),
                'sort' => Type::string(),
                'filter' => Type::string(),
            ];
        } else {
            if(isset($this->queryClasses[$typeName]['argsOne'])) {
                return $this->queryClasses[$typeName]['argsOne'];
            }

            return [
                'id' => Type::nonNull(Type::int()),
            ];
        }
    }



    public function getTableAlias( $tableName )
    {
        $alias = str_replace('_', '', ucwords($tableName, '_'));
        return lcfirst($alias);
    }



}