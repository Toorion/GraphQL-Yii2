<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests\Executor\TestClasses;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Type\Definition\ScalarType;
use YiiGraphQL\Utils\Utils;

class ComplexScalar extends ScalarType
{
    /** @var string */
    public $name = 'ComplexScalar';

    public static function create() : self
    {
        return new self();
    }

    /**
     * {@inheritdoc}
     */
    public function serialize($value)
    {
        if ($value === 'DeserializedValue') {
            return 'SerializedValue';
        }

        throw new Error('Cannot serialize value as ComplexScalar: ' . Utils::printSafe($value));
    }

    /**
     * {@inheritdoc}
     */
    public function parseValue($value)
    {
        if ($value === 'SerializedValue') {
            return 'DeserializedValue';
        }

        throw new Error('Cannot represent value as ComplexScalar: ' . Utils::printSafe($value));
    }

    /**
     * {@inheritdoc}
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        if ($valueNode->value === 'SerializedValue') {
            return 'DeserializedValue';
        }

        throw new Error('Cannot represent literal as ComplexScalar: ' . Utils::printSafe($valueNode->value));
    }
}
