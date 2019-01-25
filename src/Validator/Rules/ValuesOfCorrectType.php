<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use Exception;
use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\BooleanValueNode;
use YiiGraphQL\Language\AST\EnumValueNode;
use YiiGraphQL\Language\AST\FieldNode;
use YiiGraphQL\Language\AST\FloatValueNode;
use YiiGraphQL\Language\AST\IntValueNode;
use YiiGraphQL\Language\AST\ListValueNode;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\AST\NullValueNode;
use YiiGraphQL\Language\AST\ObjectFieldNode;
use YiiGraphQL\Language\AST\ObjectValueNode;
use YiiGraphQL\Language\AST\StringValueNode;
use YiiGraphQL\Language\AST\ValueNode;
use YiiGraphQL\Language\Printer;
use YiiGraphQL\Language\Visitor;
use YiiGraphQL\Type\Definition\EnumType;
use YiiGraphQL\Type\Definition\EnumValueDefinition;
use YiiGraphQL\Type\Definition\FieldArgument;
use YiiGraphQL\Type\Definition\InputObjectType;
use YiiGraphQL\Type\Definition\ListOfType;
use YiiGraphQL\Type\Definition\NonNull;
use YiiGraphQL\Type\Definition\ScalarType;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Utils\Utils;
use YiiGraphQL\Validator\ValidationContext;
use Throwable;
use function array_combine;
use function array_keys;
use function array_map;
use function array_values;
use function iterator_to_array;
use function sprintf;

/**
 * Value literals of correct type
 *
 * A GraphQL document is only valid if all value literals are of the type
 * expected at their position.
 */
class ValuesOfCorrectType extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        $fieldName = '';
        return [
            NodeKind::FIELD        => [
                'enter' => static function (FieldNode $node) use (&$fieldName) {
                    $fieldName = $node->name->value;
                },
            ],
            NodeKind::NULL         => static function (NullValueNode $node) use ($context, &$fieldName) {
                $type = $context->getInputType();
                if (! ($type instanceof NonNull)) {
                    return;
                }

                $context->reportError(
                    new Error(
                        self::getBadValueMessage((string) $type, Printer::doPrint($node), null, $context, $fieldName),
                        $node
                    )
                );
            },
            NodeKind::LST          => function (ListValueNode $node) use ($context, &$fieldName) {
                // Note: TypeInfo will traverse into a list's item type, so look to the
                // parent input type to check if it is a list.
                $type = Type::getNullableType($context->getParentInputType());
                if (! $type instanceof ListOfType) {
                    $this->isValidScalar($context, $node, $fieldName);

                    return Visitor::skipNode();
                }
            },
            NodeKind::OBJECT       => function (ObjectValueNode $node) use ($context, &$fieldName) {
                // Note: TypeInfo will traverse into a list's item type, so look to the
                // parent input type to check if it is a list.
                $type = Type::getNamedType($context->getInputType());
                if (! $type instanceof InputObjectType) {
                    $this->isValidScalar($context, $node, $fieldName);

                    return Visitor::skipNode();
                }
                unset($fieldName);
                // Ensure every required field exists.
                $inputFields  = $type->getFields();
                $nodeFields   = iterator_to_array($node->fields);
                $fieldNodeMap = array_combine(
                    array_map(
                        static function ($field) {
                            return $field->name->value;
                        },
                        $nodeFields
                    ),
                    array_values($nodeFields)
                );
                foreach ($inputFields as $fieldName => $fieldDef) {
                    $fieldType = $fieldDef->getType();
                    if (isset($fieldNodeMap[$fieldName]) || ! ($fieldType instanceof NonNull)) {
                        continue;
                    }

                    $context->reportError(
                        new Error(
                            self::requiredFieldMessage($type->name, $fieldName, (string) $fieldType),
                            $node
                        )
                    );
                }
            },
            NodeKind::OBJECT_FIELD => static function (ObjectFieldNode $node) use ($context) {
                $parentType = Type::getNamedType($context->getParentInputType());
                $fieldType  = $context->getInputType();
                if ($fieldType || ! ($parentType instanceof InputObjectType)) {
                    return;
                }

                $suggestions = Utils::suggestionList(
                    $node->name->value,
                    array_keys($parentType->getFields())
                );
                $didYouMean  = $suggestions
                    ? 'Did you mean ' . Utils::orList($suggestions) . '?'
                    : null;

                $context->reportError(
                    new Error(
                        self::unknownFieldMessage($parentType->name, $node->name->value, $didYouMean),
                        $node
                    )
                );
            },
            NodeKind::ENUM         => function (EnumValueNode $node) use ($context, &$fieldName) {
                $type = Type::getNamedType($context->getInputType());
                if (! $type instanceof EnumType) {
                    $this->isValidScalar($context, $node, $fieldName);
                } elseif (! $type->getValue($node->value)) {
                    $context->reportError(
                        new Error(
                            self::getBadValueMessage(
                                $type->name,
                                Printer::doPrint($node),
                                $this->enumTypeSuggestion($type, $node),
                                $context,
                                $fieldName
                            ),
                            $node
                        )
                    );
                }
            },
            NodeKind::INT          => function (IntValueNode $node) use ($context, &$fieldName) {
                $this->isValidScalar($context, $node, $fieldName);
            },
            NodeKind::FLOAT        => function (FloatValueNode $node) use ($context, &$fieldName) {
                $this->isValidScalar($context, $node, $fieldName);
            },
            NodeKind::STRING       => function (StringValueNode $node) use ($context, &$fieldName) {
                $this->isValidScalar($context, $node, $fieldName);
            },
            NodeKind::BOOLEAN      => function (BooleanValueNode $node) use ($context, &$fieldName) {
                $this->isValidScalar($context, $node, $fieldName);
            },
        ];
    }

    public static function badValueMessage($typeName, $valueName, $message = null)
    {
        return sprintf('Expected type %s, found %s', $typeName, $valueName) .
            ($message ? "; ${message}" : '.');
    }

    private function isValidScalar(ValidationContext $context, ValueNode $node, $fieldName)
    {
        // Report any error at the full type expected by the location.
        $locationType = $context->getInputType();

        if (! $locationType) {
            return;
        }

        $type = Type::getNamedType($locationType);

        if (! $type instanceof ScalarType) {
            $context->reportError(
                new Error(
                    self::getBadValueMessage(
                        (string) $locationType,
                        Printer::doPrint($node),
                        $this->enumTypeSuggestion($type, $node),
                        $context,
                        $fieldName
                    ),
                    $node
                )
            );

            return;
        }

        // Scalars determine if a literal value is valid via parseLiteral() which
        // may throw to indicate failure.
        try {
            $type->parseLiteral($node);
        } catch (Exception $error) {
            // Ensure a reference to the original error is maintained.
            $context->reportError(
                new Error(
                    self::getBadValueMessage(
                        (string) $locationType,
                        Printer::doPrint($node),
                        $error->getMessage(),
                        $context,
                        $fieldName
                    ),
                    $node
                )
            );
        } catch (Throwable $error) {
            // Ensure a reference to the original error is maintained.
            $context->reportError(
                new Error(
                    self::getBadValueMessage(
                        (string) $locationType,
                        Printer::doPrint($node),
                        $error->getMessage(),
                        $context,
                        $fieldName
                    ),
                    $node
                )
            );
        }
    }

    private function enumTypeSuggestion($type, ValueNode $node)
    {
        if ($type instanceof EnumType) {
            $suggestions = Utils::suggestionList(
                Printer::doPrint($node),
                array_map(
                    static function (EnumValueDefinition $value) {
                        return $value->name;
                    },
                    $type->getValues()
                )
            );

            return $suggestions ? 'Did you mean the enum value ' . Utils::orList($suggestions) . '?' : null;
        }
    }

    public static function badArgumentValueMessage($typeName, $valueName, $fieldName, $argName, $message = null)
    {
        return sprintf('Field "%s" argument "%s" requires type %s, found %s', $fieldName, $argName, $typeName, $valueName) .
            ($message ? sprintf('; %s', $message) : '.');
    }

    public static function requiredFieldMessage($typeName, $fieldName, $fieldTypeName)
    {
        return sprintf('Field %s.%s of required type %s was not provided.', $typeName, $fieldName, $fieldTypeName);
    }

    public static function unknownFieldMessage($typeName, $fieldName, $message = null)
    {
        return sprintf('Field "%s" is not defined by type %s', $fieldName, $typeName) .
            ($message ? sprintf('; %s', $message) : '.');
    }

    private static function getBadValueMessage($typeName, $valueName, $message = null, $context = null, $fieldName = null)
    {
        if ($context) {
            $arg = $context->getArgument();
            if ($arg) {
                return self::badArgumentValueMessage($typeName, $valueName, $fieldName, $arg->name, $message);
            }
        }
        return self::badValueMessage($typeName, $valueName, $message);
    }
}
