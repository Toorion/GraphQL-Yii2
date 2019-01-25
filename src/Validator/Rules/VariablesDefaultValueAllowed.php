<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\FragmentDefinitionNode;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\AST\SelectionSetNode;
use YiiGraphQL\Language\AST\VariableDefinitionNode;
use YiiGraphQL\Language\Visitor;
use YiiGraphQL\Type\Definition\NonNull;
use YiiGraphQL\Validator\ValidationContext;
use function sprintf;

/**
 * Variable's default value is allowed
 *
 * A GraphQL document is only valid if all variable default values are allowed
 * due to a variable not being required.
 */
class VariablesDefaultValueAllowed extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::VARIABLE_DEFINITION => static function (VariableDefinitionNode $node) use ($context) {
                $name         = $node->variable->name->value;
                $defaultValue = $node->defaultValue;
                $type         = $context->getInputType();
                if ($type instanceof NonNull && $defaultValue) {
                    $context->reportError(
                        new Error(
                            self::defaultForRequiredVarMessage(
                                $name,
                                $type,
                                $type->getWrappedType()
                            ),
                            [$defaultValue]
                        )
                    );
                }

                return Visitor::skipNode();
            },
            NodeKind::SELECTION_SET       => static function (SelectionSetNode $node) {
                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION => static function (FragmentDefinitionNode $node) {
                return Visitor::skipNode();
            },
        ];
    }

    public static function defaultForRequiredVarMessage($varName, $type, $guessType)
    {
        return sprintf(
            'Variable "$%s" of type "%s" is required and will not use the default value. Perhaps you meant to use type "%s".',
            $varName,
            $type,
            $guessType
        );
    }
}
