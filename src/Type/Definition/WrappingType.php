<?php

declare(strict_types=1);

namespace YiiGraphQL\Type\Definition;

interface WrappingType
{
    /**
     * @param bool $recurse
     *
     * @return ObjectType|InterfaceType|UnionType|ScalarType|InputObjectType|EnumType
     */
    public function getWrappedType($recurse = false);
}
