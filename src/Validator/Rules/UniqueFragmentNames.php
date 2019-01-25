<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\FragmentDefinitionNode;
use YiiGraphQL\Language\AST\NameNode;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\Visitor;
use YiiGraphQL\Validator\ValidationContext;
use function sprintf;

class UniqueFragmentNames extends ValidationRule
{
    /** @var NameNode[] */
    public $knownFragmentNames;

    public function getVisitor(ValidationContext $context)
    {
        $this->knownFragmentNames = [];

        return [
            NodeKind::OPERATION_DEFINITION => static function () {
                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION  => function (FragmentDefinitionNode $node) use ($context) {
                $fragmentName = $node->name->value;
                if (empty($this->knownFragmentNames[$fragmentName])) {
                    $this->knownFragmentNames[$fragmentName] = $node->name;
                } else {
                    $context->reportError(new Error(
                        self::duplicateFragmentNameMessage($fragmentName),
                        [$this->knownFragmentNames[$fragmentName], $node->name]
                    ));
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function duplicateFragmentNameMessage($fragName)
    {
        return sprintf('There can be only one fragment named "%s".', $fragName);
    }
}
