<?php
declare(strict_types=1);

namespace YiiGraphQL;

use yii\base\Model;
use yii\db\TableSchema;
use yii\helpers\StringHelper;
use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\ListOfType;
use yii\helpers\Inflector;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Type\YiiType;
use yii\db\ActiveRecord;
use yii\db\Query;
use YiiGraphQL\Info\InfoRegistry;

class QueryModel extends Model
{
    const DEFAULT_FIELD_TYPE = 'string';

    public $queryClasses = [];

    protected $tableSchemas = [];

    protected $foreignKeys;

    protected $relations = [];

    public function __construct(array $queryClasses)
    {
        $this->queryClasses = $queryClasses;
        return parent::__construct();
    }


    public function getRelationsOf($tableSchema, $tableName)
    {
        $key = "{$tableSchema}.{$tableName}";
        if(!isset($this->relations[$key])) {

            $this->relations[$key] = [];

            $alias = str_replace('public.', '', $key);
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
                        //$name = $row['foreign_table_name'];
                        $name = str_replace('_id', '', $row['column_name']);
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

        /*
         * From TableSchema
         */
        if(isset($tableSchema->columns[$fieldName])) {
            return YiiType::cast($tableSchema->columns[$fieldName]->type);
        }

        /*
         * From Model getters (get...())
         */
        $fields = $this->getClassFields(get_class($model));
        if(isset($fields[$fieldName])) {
            return YiiType::cast($fields[$fieldName]);
        }

        return YiiType::cast(self::DEFAULT_FIELD_TYPE);
    }


    public function getClassFields($className)
    {
        $reflectionClass = new \ReflectionClass($className);

        $fields = [];
        $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC );
        foreach( $methods as $method) {

            // Only getters can work throw magic method
            if(substr($method->name, 0, 3) != 'get') {
                continue;
            }

            // Only getters with optional/without parameters can pass throw GraphQL resolver
            foreach($method->getParameters() as $parameter) {
                if(!$parameter->isOptional()) {
                    continue 2;
                }
            }

            $name = lcfirst(substr($method->name, 3));
            $type = (null === $method->getReturnType()) ? 'string' :
                $method->getReturnType()->getName();

            $fields[$name] = $type;
        }
        return $fields;
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





    public function getTableAlias( $tableName )
    {
        $alias = str_replace('_', '', ucwords($tableName, '_'));
        return lcfirst($alias);
    }


    public function objectNameByTableSchema( TableSchema $tableSchema )
    {
        $objectName = lcfirst(str_replace('_', '', ucwords($tableSchema->name, '_')));

        if('public' != $tableSchema->schemaName) {
            $objectName = $tableSchema->schemaName . '_' . $objectName;
        }

        return $objectName;
    }


    public function objectInfoByName($name)
    {
        // scheme_camelCaseName -> scheme.camelCaseName
        $name = str_replace('_', '.', $name);

        // scheme.camelCaseName -> scheme.camel_case_name
        $name = mb_strtolower(
            preg_replace( '/([a-z0-9])([A-Z])/', "$1_$2", $name )
        );

        $multiple = ($name == Inflector::pluralize($name));
        $key = $multiple ? Inflector::singularize($name) : $name;

        return $this->objectInfoByKey($key, $multiple);
    }


    public function objectInfoByKey($key, $multiple) {
        if(!isset($this->queryClasses[$key])) {
            return null;
//            throw new \Error(
//                "Config for $name -> $alias not set"
//            );
        }

        return InfoRegistry::getInfo($this->queryClasses[$key], $multiple);
    }



    public function objectInfoByActiveRecord(ActiveRecord $parentModel, $fieldName)
    {
        $isQuery = false;
        $getter = 'get' . ucfirst($fieldName);
        $typeName = null;
        $multiple = false;
        if(method_exists($parentModel, $getter)) {
            $key = $fieldName;
            if ($fieldName == Inflector::singularize($fieldName)) {
                // hasOne
            } elseif ($fieldName == Inflector::pluralize($fieldName)) {
                // hasMany
                $multiple = true;
                $key = Inflector::singularize($key);
            }

            $reflectionClass = new \ReflectionClass($parentModel);
            $method = $reflectionClass->getMethod($getter);
            if (null !== ($type = $method->getReturnType())) {
                $typeName = $method->getReturnType()->getName();
                if (is_a($typeName, Query::class, true)) {
                    $isQuery = true;
                }
            }

            if (!$isQuery) {
                $relations = $this->getRelationsOf($parentModel->tableSchema->schemaName, $parentModel->tableSchema->name);
                // Check for relation really exists
                if (isset($relations[$key])) {
                    $isQuery = true;
                }
            }
        }

        if ($isQuery) {
            $query = $parentModel->$getter();
            if (!$query instanceof Query) {
                $modelClass = get_class($parentModel);
                throw new \Error(
                    "Method $getter of $modelClass not a Query"
                );
            }
            if ($multiple != $query->multiple) {
                $parentClass = get_class($parentModel);
                throw new \Error(
                    "Method $getter of $parentClass not a same multiple type"
                );
            }

            $modelClass = $query->modelClass;
            $tableName = $modelClass::getTableSchema()->fullName;

            if(isset($this->queryClasses[$tableName])) {
                return InfoRegistry::getInfo($this->queryClasses[$tableName], $multiple)->getType();
            }
        } elseif(null !== $typeName) {
            return $this->objectInfoByName(end(explode('\\', $typeName)));
        }

        return $this->getFieldType($parentModel, $fieldName);
    }


}