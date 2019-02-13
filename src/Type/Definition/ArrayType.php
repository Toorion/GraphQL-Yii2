<?php

declare(strict_types=1);

namespace YiiGraphQL\Type\Definition;

use Exception;
use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\ArrayValueNode;
use YiiGraphQL\Language\AST\Node;
use YiiGraphQL\Language\AST\StringValueNode;
use YiiGraphQL\Utils\Utils;
use function is_array;
use function is_object;
use function is_scalar;
use function method_exists;

/**
 * Class StringType
 */
class ArrayType extends ScalarType
{
    /** @var string */
    public $name = Type::ARRAY;

    /** @var string */
    public $description =
        'The `Array` scalar type represents array data';

    /**
     * @param mixed $value
     *
     * @return mixed|string
     *
     * @throws Error
     */
    public function serialize($value)
    {
        if (! is_array($value)) {
            throw new Error('Array cannot represent non array value: ' . Utils::printSafe($value));
        }

        return $value;
    }

    /**
     * @param mixed $value
     *
     * @return string
     *
     * @throws Error
     */
    public function parseValue($value)
    {
        return $value;
    }

    /**
     * @param Node         $valueNode
     * @param mixed[]|null $variables
     *
     * @return string|null
     *
     * @throws Exception
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof ArrayValueNode) {
            return $valueNode->value;
        }

        // Intentionally without message, as all information already in wrapped Exception
        throw new Exception();
    }
}
