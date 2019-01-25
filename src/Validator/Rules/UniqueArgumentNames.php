<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\ArgumentNode;
use YiiGraphQL\Language\AST\NameNode;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\Visitor;
use YiiGraphQL\Validator\ValidationContext;
use function sprintf;

class UniqueArgumentNames extends ValidationRule
{
    /** @var NameNode[] */
    public $knownArgNames;

    public function getVisitor(ValidationContext $context)
    {
        $this->knownArgNames = [];

        return [
            NodeKind::FIELD     => function () {
                $this->knownArgNames = [];
            },
            NodeKind::DIRECTIVE => function () {
                $this->knownArgNames = [];
            },
            NodeKind::ARGUMENT  => function (ArgumentNode $node) use ($context) {
                $argName = $node->name->value;
                if (! empty($this->knownArgNames[$argName])) {
                    $context->reportError(new Error(
                        self::duplicateArgMessage($argName),
                        [$this->knownArgNames[$argName], $node->name]
                    ));
                } else {
                    $this->knownArgNames[$argName] = $node->name;
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function duplicateArgMessage($argName)
    {
        return sprintf('There can be only one argument named "%s".', $argName);
    }
}
