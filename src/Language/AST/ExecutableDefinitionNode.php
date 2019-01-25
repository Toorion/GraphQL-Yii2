<?php

declare(strict_types=1);

namespace YiiGraphQL\Language\AST;

/**
 * export type ExecutableDefinitionNode =
 *   | OperationDefinitionNode
 *   | FragmentDefinitionNode;
 */
interface ExecutableDefinitionNode extends DefinitionNode
{
}
