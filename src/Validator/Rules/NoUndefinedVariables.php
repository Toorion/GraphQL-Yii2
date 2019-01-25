<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\AST\OperationDefinitionNode;
use YiiGraphQL\Language\AST\VariableDefinitionNode;
use YiiGraphQL\Validator\ValidationContext;
use function sprintf;

/**
 * Class NoUndefinedVariables
 *
 * A GraphQL operation is only valid if all variables encountered, both directly
 * and via fragment spreads, are defined by that operation.
 */
class NoUndefinedVariables extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        $variableNameDefined = [];

        return [
            NodeKind::OPERATION_DEFINITION => [
                'enter' => static function () use (&$variableNameDefined) {
                    $variableNameDefined = [];
                },
                'leave' => static function (OperationDefinitionNode $operation) use (&$variableNameDefined, $context) {
                    $usages = $context->getRecursiveVariableUsages($operation);

                    foreach ($usages as $usage) {
                        $node    = $usage['node'];
                        $varName = $node->name->value;

                        if (! empty($variableNameDefined[$varName])) {
                            continue;
                        }

                        $context->reportError(new Error(
                            self::undefinedVarMessage(
                                $varName,
                                $operation->name ? $operation->name->value : null
                            ),
                            [$node, $operation]
                        ));
                    }
                },
            ],
            NodeKind::VARIABLE_DEFINITION  => static function (VariableDefinitionNode $def) use (&$variableNameDefined) {
                $variableNameDefined[$def->variable->name->value] = true;
            },
        ];
    }

    public static function undefinedVarMessage($varName, $opName = null)
    {
        return $opName
            ? sprintf('Variable "$%s" is not defined by operation "%s".', $varName, $opName)
            : sprintf('Variable "$%s" is not defined.', $varName);
    }
}
