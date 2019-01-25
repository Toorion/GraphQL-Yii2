<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\FragmentDefinitionNode;
use YiiGraphQL\Language\AST\InlineFragmentNode;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\Printer;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Utils\TypeInfo;
use YiiGraphQL\Validator\ValidationContext;
use function sprintf;

class FragmentsOnCompositeTypes extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::INLINE_FRAGMENT     => static function (InlineFragmentNode $node) use ($context) {
                if (! $node->typeCondition) {
                    return;
                }

                $type = TypeInfo::typeFromAST($context->getSchema(), $node->typeCondition);
                if (! $type || Type::isCompositeType($type)) {
                    return;
                }

                $context->reportError(new Error(
                    static::inlineFragmentOnNonCompositeErrorMessage($type),
                    [$node->typeCondition]
                ));
            },
            NodeKind::FRAGMENT_DEFINITION => static function (FragmentDefinitionNode $node) use ($context) {
                $type = TypeInfo::typeFromAST($context->getSchema(), $node->typeCondition);

                if (! $type || Type::isCompositeType($type)) {
                    return;
                }

                $context->reportError(new Error(
                    static::fragmentOnNonCompositeErrorMessage(
                        $node->name->value,
                        Printer::doPrint($node->typeCondition)
                    ),
                    [$node->typeCondition]
                ));
            },
        ];
    }

    public static function inlineFragmentOnNonCompositeErrorMessage($type)
    {
        return sprintf('Fragment cannot condition on non composite type "%s".', $type);
    }

    public static function fragmentOnNonCompositeErrorMessage($fragName, $type)
    {
        return sprintf('Fragment "%s" cannot condition on non composite type "%s".', $fragName, $type);
    }
}
