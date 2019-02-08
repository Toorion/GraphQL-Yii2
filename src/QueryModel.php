<?php
declare(strict_types=1);

namespace YiiGraphQL;

use yii\base\Model;
use yii\db\TableSchema;
use yii\helpers\StringHelper;
use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\ListOfType;
use yii\db\Query;
use yii\helpers\Inflector;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Error\Error;
use YiiGraphQL\Info;

class QueryModel extends Model
{

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

            $this->relations[$key] = [];

            $alias = str_replace('public.', '', $key);
            if(isset($this->queryClasses[$alias]) && isset($this->queryClasses[$alias]['relations'])) {
                foreach ($this->queryClasses[$alias]['relations'] as $relationName) {
                    $this->relations[$alias][lcfirst($relationName)] = ['name' => ucfirst($relationName)];
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



    public function typeGraphQL($stringType = 'string')
    {
        switch($stringType) {
            case "integer":
            case "int":
            case "smallint":
            case "bigint":
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
//
//        $name = mb_strtolower(
//            preg_replace( '/([a-z0-9])([A-Z])/', "$1_$2", $name )
//        );

        $multiple = ($name == Inflector::pluralize($name));
        $alias = $multiple ? Inflector::singularize($name) : $name;

        if(!isset($this->queryClasses[$alias])) {
            throw new Error(
                "Config for $name -> $alias not set"
            );
        }

        return new Info($this, $this->queryClasses[$alias], $multiple);

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


    public function getDocumentation()
    {
        $types = [
            [
                "kind" => "OBJECT",
                "name" => "Query",
                "description" => null,
                "fields" => null,
                "inputFields" => null,
                "interfaces" => [],
                "enumValues" => null,
                "possibleTypes" => null
            ],
            [
                "kind" => "SCALAR",
                "name" => "Int",
                "description" =>  "The `Int` scalar type represents non-fractional signed whole numeric\nvalues. Int can represent values between -(2^31) and 2^31 - 1. ",
                "fields" => null,
                "inputFields" => null,
                "interfaces" => null,
                "enumValues" => null,
                "possibleTypes" => null
            ],
            [
                "kind" => "SCALAR",
                "name" => "String",
                "description" => "The `String` scalar type represents textual data, represented as UTF-8\ncharacter sequences. The String type is most often used by GraphQL to\nrepresent free-form human-readable text.",
                "fields" => null,
                "inputFields" => null,
                "interfaces" => null,
                "enumValues" => null,
                "possibleTypes" => null
            ],
            [
                "kind" => "SCALAR",
                "name" => "Boolean",
                "description" => "The `Boolean` scalar type represents `true` or `false`.",
                "fields" => null,
                "inputFields" => null,
                "interfaces" => null,
                "enumValues" => null,
                "possibleTypes" => null
            ]
        ];
        $queryFields = [];
        foreach($this->queryClasses as $name => $row) {
            $name = str_replace('.', '_', $this->getTableAlias($name));

            $queryFields[] = [
                "name" => $name,
                "description" => $row['description'] ?? null,
                "args" => [
                    [
                        "name" => "id",
                        "description" => null,
                        "type" => [
                            "kind" => "NON_NULL",
                            "name" => null,
                            "ofType" => [
                                "kind" => "SCALAR",
                                "name" => "Int",
                                "ofType" => null
                            ]
                        ],
                        "defaultValue" => null
                    ]
                ],
                "type" => [
                    "kind" => "OBJECT",
                    "name" => $name,
                    "ofType" => null
                ],
                "isDeprecated" => false,
                "deprecationReason" => null
            ];

            $types[] = [
                "kind" => "OBJECT",
                "name" => $name,
                "description" => $row['description'] ?? null,
                "fields" => $this->getFieldsDoc($row),
                "inputFields" => null,
                "interfaces" => [],
                "enumValues" => null,
                "possibleTypes" => null
            ];

        }

        $types[0]['fields'] = $queryFields;

        return [
            'queryType' => [
                'name' => 'Query'
            ],
            'mutationType' => null,
            'subscriptionType' => null,
            'types' => $types,
            "directives" => [
                [
                    "name" => "include",
                    "description" => "Directs the executor to include this field or fragment only when the `if` argument is true.",
                    "locations" => [
                        "FIELD",
                        "FRAGMENT_SPREAD",
                        "INLINE_FRAGMENT"
                    ],
                    "args" => [
                        [
                            "name" => "if",
                            "description" => "Included when true.",
                            "type" => [
                                "kind" => "NON_NULL",
                                "name" => null,
                                "ofType" => [
                                    "kind" => "SCALAR",
                                    "name" => "Boolean",
                                    "ofType" => null
                                ]
                            ],
                            "defaultValue" => null
                        ]
                    ]
                ],
                [
                    "name" => "skip",
                    "description" => "Directs the executor to skip this field or fragment when the `if` argument is true.",
                    "locations" => [
                        "FIELD",
                        "FRAGMENT_SPREAD",
                        "INLINE_FRAGMENT"
                    ],
                    "args" => [
                        [
                            "name" => "if",
                            "description" => "Skipped when true.",
                            "type" => [
                            "kind" => "NON_NULL",
                            "name" => null,
                            "ofType" => [
                                    "kind" => "SCALAR",
                                    "name" => "Boolean",
                                    "ofType" => null
                                ]
                            ],
                            "defaultValue" => null
                        ]
                    ]
                ],
                [
                    "name" => "deprecated",
                    "description" => "Marks an element of a GraphQL schema as no longer supported.",
                    "locations" => [
                        "FIELD_DEFINITION",
                        "ENUM_VALUE"
                    ],
                    "args" => [
                            [
                            "name" => "reason",
                            "description" => "Explains why this element was deprecated, usually also including a suggestion for how to access supported similar data. Formatted in [Markdown](https://daringfireball.net/projects/markdown/).",
                            "type" => [
                                "kind" => "SCALAR",
                                "name" =>  "String",
                                "ofType" =>  null
                            ],
                            "defaultValue" => "\"No longer supported\""
                        ]
                    ]
                ]
            ]
        ];
    }


    public function getFieldsDoc($config)
    {
        $tableSchema = call_user_func([$config['class'], 'getTableSchema']);

        $className = $config['class'];

        $model = new $className;

        $labels = $model->attributeLabels();

        $types = [];
        foreach($tableSchema->columns as $name => $schema) {
            $types[] = [
                "name" => $name,
                "description" => $labels[$name] ?? null,
                "args" => [],
                "type" => [
                    "kind" => "SCALAR",
                    "name" => $this->typeGraphQL($schema->type)->toString(),
                    "ofType" => null
                ],
                "isDeprecated" => false,
                "deprecationReason" => null
            ];
        }

        $relations = $this->getRelationsOf($model->tableSchema->schemaName, $model->tableSchema->name);

        foreach($relations as $relation) {

            /*
             * Singularize relation
             */
            $relationName = $relation['name'];
            $methodName = 'get' . $relationName;
            if (method_exists($model, $methodName)) {
                $query = $model->$methodName();
                if ($query instanceof Query) {
//                    $modelClass = $query->modelClass;
//                    $multiple = $query->multiple;

                    /** @var TableSchema $tbSchema */
                    $tbSchema = call_user_func([$query->modelClass, 'getTableSchema']);

                    $types[] = [
                        "name" => $relationName,
                        "description" => $labels[lcfirst($relationName)] ?? null,
                        "args" => [],
                        "type" => [
                            "kind" => "OBJECT",
                            "name" => $this->objectNameByTableSchema($tbSchema),
                            "ofType" => null
                        ],
                        "isDeprecated" => false,
                        "deprecationReason" => null
                    ];
                }
            }

            /*
             * Pluralize relations
             */
            $relationName = Inflector::pluralize($relation['name']);
            $methodName = 'get' . $relationName;
            if (method_exists($model, $methodName)) {
                $query = $model->$methodName();
                if ($query instanceof Query) {
//                    $modelClass = $query->modelClass;
//                    $multiple = $query->multiple;

                    /** @var TableSchema $tbSchema */
                    $tbSchema = call_user_func([$query->modelClass, 'getTableSchema']);

                    if(isset($this->queryClasses[$tbSchema->fullName])) {
                        $types[] = [
                            "name" => $relationName,
                            "description" => $labels[lcfirst($relationName)] ?? null,
                            "args" => [],
                            "type" => [
                                "kind" => "LIST",
                                "name" => null,
                                "ofType" => [
                                    "kind" => "OBJECT",
                                    "name" => $this->objectNameByTableSchema($tbSchema),
                                    "ofType" => null
                                ]
                            ],
                            "isDeprecated" => false,
                            "deprecationReason" => null
                        ];
                    }
                }
            }
        }

        return $types;
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

}