<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\FragmentSpreadNode;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Validator\ValidationContext;
use function sprintf;

class KnownFragmentNames extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::FRAGMENT_SPREAD => static function (FragmentSpreadNode $node) use ($context) {
                $fragmentName = $node->name->value;
                $fragment     = $context->getFragment($fragmentName);
                if ($fragment) {
                    return;
                }

                $context->reportError(new Error(
                    self::unknownFragmentMessage($fragmentName),
                    [$node->name]
                ));
            },
        ];
    }

    /**
     * @param string $fragName
     */
    public static function unknownFragmentMessage($fragName)
    {
        return sprintf('Unknown fragment "%s".', $fragName);
    }
}
