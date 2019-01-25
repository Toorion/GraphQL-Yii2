<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\NameNode;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\AST\OperationDefinitionNode;
use YiiGraphQL\Language\Visitor;
use YiiGraphQL\Validator\ValidationContext;
use function sprintf;

class UniqueOperationNames extends ValidationRule
{
    /** @var NameNode[] */
    public $knownOperationNames;

    public function getVisitor(ValidationContext $context)
    {
        $this->knownOperationNames = [];

        return [
            NodeKind::OPERATION_DEFINITION => function (OperationDefinitionNode $node) use ($context) {
                $operationName = $node->name;

                if ($operationName) {
                    if (empty($this->knownOperationNames[$operationName->value])) {
                        $this->knownOperationNames[$operationName->value] = $operationName;
                    } else {
                        $context->reportError(new Error(
                            self::duplicateOperationNameMessage($operationName->value),
                            [$this->knownOperationNames[$operationName->value], $operationName]
                        ));
                    }
                }

                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION  => static function () {
                return Visitor::skipNode();
            },
        ];
    }

    public static function duplicateOperationNameMessage($operationName)
    {
        return sprintf('There can be only one operation named "%s".', $operationName);
    }
}
