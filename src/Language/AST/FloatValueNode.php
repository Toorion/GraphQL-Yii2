<?php

declare(strict_types=1);

namespace YiiGraphQL\Language\AST;

class FloatValueNode extends Node implements ValueNode
{
    /** @var string */
    public $kind = NodeKind::FLOAT;

    /** @var string */
    public $value;
}
