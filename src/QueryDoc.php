<?php
declare(strict_types=1);

namespace YiiGraphQL;

use yii\db\Query;
use yii\helpers\Inflector;
use YiiGraphQL\Type\Definition\NonNull;
use YiiGraphQL\Type\Definition\WrappingType;

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
        foreach($this->queryModel->queryClasses as $name => $row) {
            $name = str_replace('.', '_', $this->queryModel->getTableAlias($name));

            // Singular
            if(null !== ($objectInfo = $this->buildObjectType($name, $name, $row['description'] ?? null))) {
                $queryFields[] = $objectInfo;
            }

            // Plural
            $plural = Inflector::pluralize($name);
            if(null !== ($objectInfo = $this->buildObjectType($plural, $name, $row['description'] ?? null))) {
                $queryFields[] = $objectInfo;
            }

            // Singular
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

        return $types;
    }



    protected function buildObjectType($name, $objectName, $description)
    {
        if(null === ($info = $this->queryModel->discoverObjectInfo($name))) {
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
                            "ofType" => null
                        ]
                    ],
                    "defaultValue" => null
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
                    "defaultValue" => null
                ];
            }

        }

        return [
            "name" => $name,
            "description" => $description,
            "args" => $args,
            "type" => [
                "kind" => "OBJECT",
                "name" => $objectName,
                "ofType" => null
            ],
            "isDeprecated" => false,
            "deprecationReason" => null
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
                    "name" => $this->queryModel->typeGraphQL($schema->type)->toString(),
                    "ofType" => null
                ],
                "isDeprecated" => false,
                "deprecationReason" => null
            ];
        }

        $relations = $this->queryModel->getRelationsOf($model->tableSchema->schemaName, $model->tableSchema->name);

        foreach($relations as $relationKey => $relation) {

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
                        "name" => $relationKey,
                        "description" => $labels[lcfirst($relationName)] ?? null,
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

                    if(isset($this->queryModel->queryClasses[$tbSchema->fullName])) {
                        $types[] = [
                            "name" => lcfirst($relationName),
                            "description" => $labels[lcfirst($relationName)] ?? null,
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
                    }
                }
            }
        }

        return $types;
    }




}