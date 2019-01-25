<?php

declare(strict_types=1);

namespace YiiGraphQL\Language\AST;

/**
 * export type TypeExtensionNode =
 * | ScalarTypeExtensionNode
 * | ObjectTypeExtensionNode
 * | InterfaceTypeExtensionNode
 * | UnionTypeExtensionNode
 * | EnumTypeExtensionNode
 * | InputObjectTypeExtensionNode;
 */
interface TypeExtensionNode extends TypeSystemDefinitionNode
{
}
