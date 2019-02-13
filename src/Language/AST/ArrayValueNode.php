<?php

declare(strict_types=1);

namespace YiiGraphQL\Language\AST;

class ArrayValueNode extends Node implements ValueNode
{
    /** @var string */
    public $kind = NodeKind::ARRAY;

    /** @var array */
    public $value;

    /** @var bool|null */
    public $block;
}
