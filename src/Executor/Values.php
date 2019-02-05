<?php

declare(strict_types=1);

namespace YiiGraphQL\Executor;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\ArgumentNode;
use YiiGraphQL\Language\AST\DirectiveNode;
use YiiGraphQL\Language\AST\EnumValueDefinitionNode;
use YiiGraphQL\Language\AST\FieldDefinitionNode;
use YiiGraphQL\Language\AST\FieldNode;
use YiiGraphQL\Language\AST\FragmentSpreadNode;
use YiiGraphQL\Language\AST\InlineFragmentNode;
use YiiGraphQL\Language\AST\Node;
use YiiGraphQL\Language\AST\NodeList;
use YiiGraphQL\Language\AST\ValueNode;
use YiiGraphQL\Language\AST\VariableDefinitionNode;
use YiiGraphQL\Language\AST\VariableNode;
use YiiGraphQL\Language\Printer;
use YiiGraphQL\Type\Definition\Directive;
use YiiGraphQL\Type\Definition\FieldDefinition;
use YiiGraphQL\Type\Definition\InputType;
use YiiGraphQL\Type\Definition\NonNull;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Type\Schema;
use YiiGraphQL\Utils\AST;
use YiiGraphQL\Utils\TypeInfo;
use YiiGraphQL\Utils\Utils;
use YiiGraphQL\Utils\Value;
use stdClass;
use Throwable;
use function array_key_exists;
use function array_map;
use function sprintf;

class Values
{
    /**
     * Prepares an object map of variables of the correct type based on the provided
     * variable definitions and arbitrary input. If the input cannot be coerced
     * to match the variable definitions, a Error will be thrown.
     *
     * @param VariableDefinitionNode[] $varDefNodes
     * @param mixed[]                  $inputs
     *
     * @return mixed[]
     */
    public static function getVariableValues($varDefNodes, array $inputs)
    {
        $errors        = [];
        $coercedValues = [];
        foreach ($varDefNodes as $varDefNode) {
            $varName = $varDefNode->variable->name->value;

            $varType = Type::getStandardTypes()[$varDefNode->type->name->value];

            if (Type::isInputType($varType)) {
                if (array_key_exists($varName, $inputs)) {
                    $value   = $inputs[$varName];
                    $coerced = Value::coerceValue($value, $varType, $varDefNode);
                    /** @var Error[] $coercionErrors */
                    $coercionErrors = $coerced['errors'];
                    if (empty($coercionErrors)) {
                        $coercedValues[$varName] = $coerced['value'];
                    } else {
                        $messagePrelude = sprintf(
                            'Variable "$%s" got invalid value %s; ',
                            $varName,
                            Utils::printSafeJson($value)
                        );

                        foreach ($coercionErrors as $error) {
                            $errors[] = new Error(
                                $messagePrelude . $error->getMessage(),
                                $error->getNodes(),
                                $error->getSource(),
                                $error->getPositions(),
                                $error->getPath(),
                                $error,
                                $error->getExtensions()
                            );
                        }
                    }
                } else {
                    if ($varType instanceof NonNull) {
                        $errors[] = new Error(
                            sprintf(
                                'Variable "$%s" of required type "%s" was not provided.',
                                $varName,
                                $varType
                            ),
                            [$varDefNode]
                        );
                    } elseif ($varDefNode->defaultValue) {
                        $coercedValues[$varName] = AST::valueFromAST($varDefNode->defaultValue, $varType);
                    }
                }
            } else {
                $errors[] = new Error(
                    sprintf(
                        'Variable "$%s" expected value of type "%s" which cannot be used as an input type.',
                        $varName,
                        Printer::doPrint($varDefNode->type)
                    ),
                    [$varDefNode->type]
                );
            }
        }

        if (! empty($errors)) {
            return [$errors, null];
        }

        return [null, $coercedValues];
    }

    /**
     * Prepares an object map of argument values given a directive definition
     * and a AST node which may contain directives. Optionally also accepts a map
     * of variable values.
     *
     * If the directive does not exist on the node, returns undefined.
     *
     * @param FragmentSpreadNode|FieldNode|InlineFragmentNode|EnumValueDefinitionNode|FieldDefinitionNode $node
     * @param mixed[]|null                                                                                $variableValues
     *
     * @return mixed[]|null
     */
    public static function getDirectiveValues(Directive $directiveDef, $node, $variableValues = null)
    {
        if (isset($node->directives) && $node->directives instanceof NodeList) {
            $directiveNode = Utils::find(
                $node->directives,
                static function (DirectiveNode $directive) use ($directiveDef) {
                    return $directive->name->value === $directiveDef->name;
                }
            );

            if ($directiveNode !== null) {
                return self::getArgumentValues($directiveDef, $directiveNode, $variableValues);
            }
        }

        return null;
    }

    /**
     * Prepares an object map of argument values given a list of argument
     * definitions and list of argument AST nodes.
     *
     * @param FieldDefinition|Directive $def
     * @param FieldNode|DirectiveNode   $node
     * @param mixed[]                   $variableValues
     *
     * @return mixed[]
     *
     * @throws Error
     */
    public static function getArgumentValues($args, $node, $variableValues = null)
    {
        if (empty($args)) {
            return [];
        }

        $argumentNodes = $node->arguments;
        if (empty($argumentNodes)) {
            return [];
        }

        $argumentValueMap = [];
        foreach ($argumentNodes as $argumentNode) {
            $argumentValueMap[$argumentNode->name->value] = $argumentNode->value;
        }

        return static::getArgumentValuesForMap($args, $argumentValueMap, $variableValues, $node);
    }

    /**
     * @param FieldDefinition|Directive $fieldDefinition
     * @param ArgumentNode[]            $argumentValueMap
     * @param mixed[]                   $variableValues
     * @param Node|null                 $referenceNode
     *
     * @return mixed[]
     *
     * @throws Error
     */
    public static function getArgumentValuesForMap($argumentDefinitions, $argumentValueMap, $variableValues = null, $referenceNode = null)
    {
        $coercedValues       = [];

        foreach ($argumentDefinitions as $argumentDefinition) {
            $name              = $argumentDefinition->name;
            $argType           = $argumentDefinition->getType();
            $argumentValueNode = $argumentValueMap[$name] ?? null;

            if (! $argumentValueNode) {
                if ($argumentDefinition->defaultValueExists()) {
                    $coercedValues[$name] = $argumentDefinition->defaultValue;
                } elseif ($argType instanceof NonNull) {
                    throw new Error(
                        'Argument "' . $name . '" of required type ' .
                        '"' . Utils::printSafe($argType) . '" was not provided.',
                        $referenceNode
                    );
                }
            } elseif ($argumentValueNode instanceof VariableNode) {
                $variableName = $argumentValueNode->name->value;

                if ($variableValues && array_key_exists($variableName, $variableValues)) {
                    // Note: this does not check that this variable value is correct.
                    // This assumes that this query has been validated and the variable
                    // usage here is of the correct type.
                    $coercedValues[$name] = $variableValues[$variableName];
                } elseif ($argumentDefinition->defaultValueExists()) {
                    $coercedValues[$name] = $argumentDefinition->defaultValue;
                } elseif ($argType instanceof NonNull) {
                    throw new Error(
                        'Argument "' . $name . '" of required type "' . Utils::printSafe($argType) . '" was ' .
                        'provided the variable "$' . $variableName . '" which was not provided ' .
                        'a runtime value.',
                        [$argumentValueNode]
                    );
                }
            } else {
                $valueNode    = $argumentValueNode;
                $coercedValue = AST::valueFromAST($valueNode, $argType, $variableValues);
                if (Utils::isInvalid($coercedValue)) {
                    // Note: ValuesOfCorrectType validation should catch this before
                    // execution. This is a runtime check to ensure execution does not
                    // continue with an invalid argument value.
                    throw new Error(
                        'Argument "' . $name . '" has invalid value ' . Printer::doPrint($valueNode) . '.',
                        [$argumentValueNode]
                    );
                }
                $coercedValues[$name] = $coercedValue;
            }
        }

        return $coercedValues;
    }

    /**
     * @deprecated as of 8.0 (Moved to \GraphQL\Utils\AST::valueFromAST)
     *
     * @param ValueNode    $valueNode
     * @param mixed[]|null $variables
     *
     * @return mixed[]|stdClass|null
     */
    public static function valueFromAST($valueNode, InputType $type, ?array $variables = null)
    {
        return AST::valueFromAST($valueNode, $type, $variables);
    }

    /**
     * @deprecated as of 0.12 (Use coerceValue() directly for richer information)
     *
     * @param mixed[] $value
     *
     * @return string[]
     */
    public static function isValidPHPValue($value, InputType $type)
    {
        $errors = Value::coerceValue($value, $type)['errors'];

        return $errors
            ? array_map(
                static function (Throwable $error) {
                    return $error->getMessage();
                },
                $errors
            ) : [];
    }
}
