<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\FieldNode;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Validator\ValidationContext;
use function sprintf;

class ScalarLeafs extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::FIELD => static function (FieldNode $node) use ($context) {
                $type = $context->getType();
                if (! $type) {
                    return;
                }

                if (Type::isLeafType(Type::getNamedType($type))) {
                    if ($node->selectionSet) {
                        $context->reportError(new Error(
                            self::noSubselectionAllowedMessage($node->name->value, $type),
                            [$node->selectionSet]
                        ));
                    }
                } elseif (! $node->selectionSet) {
                    $context->reportError(new Error(
                        self::requiredSubselectionMessage($node->name->value, $type),
                        [$node]
                    ));
                }
            },
        ];
    }

    public static function noSubselectionAllowedMessage($field, $type)
    {
        return sprintf('Field "%s" of type "%s" must not have a sub selection.', $field, $type);
    }

    public static function requiredSubselectionMessage($field, $type)
    {
        return sprintf('Field "%s" of type "%s" must have a sub selection.', $field, $type);
    }
}
