<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\DocumentNode;
use YiiGraphQL\Language\AST\FragmentDefinitionNode;
use YiiGraphQL\Language\AST\Node;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\AST\OperationDefinitionNode;
use YiiGraphQL\Language\Visitor;
use YiiGraphQL\Validator\ValidationContext;
use function sprintf;

/**
 * Executable definitions
 *
 * A GraphQL document is only valid for execution if all definitions are either
 * operation or fragment definitions.
 */
class ExecutableDefinitions extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        return [
            NodeKind::DOCUMENT => static function (DocumentNode $node) use ($context) {
                /** @var Node $definition */
                foreach ($node->definitions as $definition) {
                    if ($definition instanceof OperationDefinitionNode ||
                        $definition instanceof FragmentDefinitionNode
                    ) {
                        continue;
                    }

                    $context->reportError(new Error(
                        self::nonExecutableDefinitionMessage($definition->name->value),
                        [$definition->name]
                    ));
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function nonExecutableDefinitionMessage($defName)
    {
        return sprintf('The "%s" definition is not executable.', $defName);
    }
}
