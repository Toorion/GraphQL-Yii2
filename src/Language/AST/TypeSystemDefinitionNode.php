<?php

declare(strict_types=1);

namespace YiiGraphQL\Language\AST;

/**
 * export type TypeSystemDefinitionNode =
 * | SchemaDefinitionNode
 * | TypeDefinitionNode
 * | TypeExtensionNode
 * | DirectiveDefinitionNode
 */
interface TypeSystemDefinitionNode extends DefinitionNode
{
}
