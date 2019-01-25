<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\NodeKind;
use YiiGraphQL\Language\AST\SchemaDefinitionNode;
use YiiGraphQL\Validator\ValidationContext;

/**
 * Lone Schema definition
 *
 * A GraphQL document is only valid if it contains only one schema definition.
 */
class LoneSchemaDefinition extends ValidationRule
{
    public function getVisitor(ValidationContext $context)
    {
        $oldSchema      = $context->getSchema();
        $alreadyDefined = $oldSchema !== null ? (
            $oldSchema->getAstNode() ||
            $oldSchema->getQueryType() ||
            $oldSchema->getMutationType() ||
            $oldSchema->getSubscriptionType()
        ) : false;

        $schemaDefinitionsCount = 0;

        return [
            NodeKind::SCHEMA_DEFINITION => static function (SchemaDefinitionNode $node) use ($alreadyDefined, $context, &$schemaDefinitionsCount) {
                if ($alreadyDefined !== false) {
                    $context->reportError(new Error('Cannot define a new schema within a schema extension.', $node));
                    return;
                }

                if ($schemaDefinitionsCount > 0) {
                    $context->reportError(new Error('Must provide only one schema definition.', $node));
                }

                ++$schemaDefinitionsCount;
            },
        ];
    }
}
