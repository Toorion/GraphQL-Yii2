<?php

declare(strict_types=1);

namespace YiiGraphQL\Experimental\Executor;

use YiiGraphQL\Language\AST\ValueNode;
use YiiGraphQL\Type\Definition\InputType;

/**
 * @internal
 */
interface Runtime
{
    public function evaluate(ValueNode $valueNode, InputType $type);

    public function addError($error);
}
