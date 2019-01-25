<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\DirectiveDefinitionNode;
use YiiGraphQL\Language\AST\DirectiveNode;
use YiiGraphQL\Language\AST\InputValueDefinitionNode;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\AST\NodeList;
use YiiGraphQL\Type\Definition\Directive;
use YiiGraphQL\Type\Definition\FieldArgument;
use YiiGraphQL\Validator\ValidationContext;
use function array_map;
use function in_array;
use function iterator_to_array;

/**
 * Known argument names on directives
 *
 * A GraphQL directive is only valid if all supplied arguments are defined by
 * that field.
 */
class KnownArgumentNamesOnDirectives extends ValidationRule
{
    protected static function unknownDirectiveArgMessage(string $argName, string $directionName)
    {
        return 'Unknown argument "' . $argName . '" on directive "@' . $directionName . '".';
    }

    public function getVisitor(ValidationContext $context)
    {
        $directiveArgs     = [];
        $schema            = $context->getSchema();
        $definedDirectives = $schema !== null ? $schema->getDirectives() : Directive::getInternalDirectives();

        foreach ($definedDirectives as $directive) {
            $directiveArgs[$directive->name] = array_map(
                static function (FieldArgument $arg) : string {
                    return $arg->name;
                },
                $directive->args
            );
        }

        $astDefinitions = $context->getDocument()->definitions;
        foreach ($astDefinitions as $def) {
            if (! ($def instanceof DirectiveDefinitionNode)) {
                continue;
            }

            $name = $def->name->value;
            if ($def->arguments !== null) {
                $arguments = $def->arguments;

                if ($arguments instanceof NodeList) {
                    $arguments = iterator_to_array($arguments->getIterator());
                }

                $directiveArgs[$name] = array_map(static function (InputValueDefinitionNode $arg) : string {
                    return $arg->name->value;
                }, $arguments);
            } else {
                $directiveArgs[$name] = [];
            }
        }

        return [
            NodeKind::DIRECTIVE => static function (DirectiveNode $directiveNode) use ($directiveArgs, $context) {
                $directiveName = $directiveNode->name->value;
                $knownArgs     = $directiveArgs[$directiveName] ?? null;

                if ($directiveNode->arguments === null || ! $knownArgs) {
                    return;
                }

                foreach ($directiveNode->arguments as $argNode) {
                    $argName = $argNode->name->value;
                    if (in_array($argName, $knownArgs, true)) {
                        continue;
                    }

                    $context->reportError(new Error(
                        self::unknownDirectiveArgMessage($argName, $directiveName),
                        [$argNode]
                    ));
                }
            },
        ];
    }
}
