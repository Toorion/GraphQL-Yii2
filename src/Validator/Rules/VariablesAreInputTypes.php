<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\AST\VariableDefinitionNode;
use YiiGraphQL\Language\Printer;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Utils\TypeInfo;
use YiiGraphQL\Validator\ValidationContext;
use function sprintf;

class VariablesAreInputTypes extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::VARIABLE_DEFINITION => static function (VariableDefinitionNode $node) use ($context) {
                $type = TypeInfo::typeFromAST($context->getSchema(), $node->type);

                // If the variable type is not an input type, return an error.
                if (! $type || Type::isInputType($type)) {
                    return;
                }

                $variableName = $node->variable->name->value;
                $context->reportError(new Error(
                    self::nonInputTypeOnVarMessage($variableName, Printer::doPrint($node->type)),
                    [$node->type]
                ));
            },
        ];
    }

    public static function nonInputTypeOnVarMessage($variableName, $typeName)
    {
        return sprintf('Variable "$%s" cannot be non-input type "%s".', $variableName, $typeName);
    }
}
