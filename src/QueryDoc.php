<?php
declare(strict_types=1);

namespace YiiGraphQL;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\db\Query;
use yii\helpers\Inflector;
use YiiGraphQL\Type\Definition\NonNull;
use YiiGraphQL\Type\Definition\WrappingType;
use YiiGraphQL\Type\YiiType;

class QueryDoc
{

    protected $queryModel;



    public function __construct(QueryModel $queryModel)
    {
        $this->queryModel = $queryModel;
    }


    public function build()
    {
        return [
            'queryType' => [
                'name' => 'Query'
            ],
            'mutationType' => null,
            'subscriptionType' => null,
            'types' => $this->buildRootTypes(),
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



    protected function buildRootTypes()
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
        foreach($this->queryModel->queryClasses as $key => $row) {
            if(is_a($row['class'], ActiveRecord::class, true)) {
                $name = str_replace('.', '_', $this->queryModel->getTableAlias($key));

                // Singular
                if (null !== ($objectInfo = $this->buildObjectType($key, $name, $name, false, $row['description'] ?? null))) {
                    $queryFields[] = $objectInfo;
                }

                // Plural
                $plural = Inflector::pluralize($name);
                if (null !== ($objectInfo = $this->buildObjectType($key, $plural, $name, true, $row['description'] ?? null))) {
                    $queryFields[] = $objectInfo;
                }

                $fields = $this->getTableFieldsDoc($row);
            } else {
                $name = lcfirst(str_replace('_', '', ucwords($key, '_')));
                if (null !== ($objectInfo = $this->buildObjectType($key, $name, $name, false,$row['description'] ?? null))) {
                    $queryFields[] = $objectInfo;
                }

                $fields = $this->getObjectFieldsDoc($row);
            }

            // Singular
            $types[] = [
                "kind" => "OBJECT",
                "name" => $name,
                "description" => $row['description'] ?? null,
                "fields" => $fields,
                "inputFields" => null,
                "interfaces" => [],
                "enumValues" => null,
                "possibleTypes" => null
            ];
        }


        $types[0]['fields'] = $queryFields;

        return $types;
    }



    protected function buildObjectType($key, $name, $typeName, $multiple, $description)
    {
        if(null === ($info = $this->queryModel->objectInfoByKey($key, $multiple))) {
            return null;
        }

        $args = [];
        foreach($info->getArgs() as $argName => $argType) {
            if($argType instanceof NonNull) {
                $args[] = [
                    "name" => $argName,
                    "description" => null,
                    "type" => [
                        "kind" => "NON_NULL",
                        "name" => null,
                        "ofType" => [
                            "kind" => "SCALAR",
                            "name" => $argType->getWrappedType()->toString(),
                            "ofType" => null,
                        ]
                    ],
                    "defaultValue" => isset($argType->config['defaultValue']) ? $argType->config['defaultValue'] : null,
                ];
            } else {
                $args[] = [
                    "name" => $argName,
                    "description" => null,
                    "type" => [
                        "kind" => "SCALAR",
                        "name" => $argType->toString(),
                        "ofType" => null
                    ],
                    "defaultValue" => isset($argType->config['defaultValue']) ? $argType->config['defaultValue'] : null,
                ];
            }

        }

        return [
            "name" => $name,
            "description" => $description,
            "args" => $args,
            "type" => [
                "kind" => "OBJECT",
                "name" => $typeName,
                "ofType" => null
            ],
            "isDeprecated" => false,
            "deprecationReason" => null
        ];
    }




    protected function getTableFieldsDoc($config)
    {
        $tableSchema = call_user_func([$config['class'], 'getTableSchema']);

        $className = $config['class'];

        $model = new $className;

        $labels = $model->attributeLabels();

        $types = [];
        foreach($tableSchema->columns as $name => $schema) {
            if(null === ($type = YiiType::cast($schema->type))) {
                continue;
            }

            $types[] = [
                "name" => $name,
                "description" => $labels[$name] ?? null,
                "args" => [],
                "type" => [
                    "kind" => "SCALAR",
                    "name" => $type->toString(),
                    "ofType" => null
                ],
                "isDeprecated" => false,
                "deprecationReason" => null
            ];
        }

        $relations = $this->queryModel->getRelationsOf($model->tableSchema->schemaName, $model->tableSchema->name);

        $reflectionClass = new \ReflectionClass($className);
        $methods = $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC );
        foreach ($methods as $method) {

            $relationKey = substr($method->name, 0, 3) == 'get' ?
                lcfirst(substr($method->name, 3)) :
                $method->name;

            if(null === ($returnType = $method->getReturnType()) || !is_a($returnType->getName(), Query::class, true)) {
                if(!isset($relations[$relationKey])) {
                    continue;
                }
            }

            $query = $model->{$method->name}();
            if(!$query instanceof  Query) {
                continue;
            }
            $tbSchema = call_user_func([$query->modelClass, 'getTableSchema']);
            if(!isset($this->queryModel->queryClasses[$tbSchema->fullName])) {
                continue;
            }

            if($query->multiple) {
                $types[] = [
                    "name" => $relationKey,
                    "description" => null,
                    "args" => [],
                    "type" => [
                        "kind" => "LIST",
                        "name" => null,
                        "ofType" => [
                            "kind" => "OBJECT",
                            "name" => $this->queryModel->objectNameByTableSchema($tbSchema),
                            "ofType" => null
                        ]
                    ],
                    "isDeprecated" => false,
                    "deprecationReason" => null
                ];
            } else {
                $types[] = [
                    "name" => $relationKey,
                    "description" => null,
                    "args" => [],
                    "type" => [
                        "kind" => "OBJECT",
                        "name" => $this->queryModel->objectNameByTableSchema($tbSchema),
                        "ofType" => null
                    ],
                    "isDeprecated" => false,
                    "deprecationReason" => null
                ];
            }
        }

        return $types;
    }



    protected function getObjectFieldsDoc($config)
    {
        $className = $config['class'];
        $fields = $className::objectFields();

        $types = [];
        foreach($fields as $name => $baseType) {
            if(null === ($type = YiiType::cast($baseType))) {
                continue;
            }

            $types[] = [
                "name" => $name,
                "description" => $labels[$name] ?? null,
                "args" => [],
                "type" => [
                    "kind" => "SCALAR",
                    "name" => $type->toString(),
                    "ofType" => null
                ],
                "isDeprecated" => false,
                "deprecationReason" => null
            ];
        }

        return $types;
    }


}