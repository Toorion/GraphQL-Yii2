<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests\Type;

use YiiGraphQL\Error\InvariantViolation;
use YiiGraphQL\Type\Definition\Directive;
use YiiGraphQL\Type\Definition\InputObjectType;
use YiiGraphQL\Type\Definition\InterfaceType;
use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Type\Schema;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    /** @var InterfaceType */
    private $interfaceType;

    /** @var ObjectType */
    private $implementingType;

    /** @var InputObjectType */
    private $directiveInputType;

    /** @var InputObjectType */
    private $wrappedDirectiveInputType;

    /** @var Directive */
    private $directive;

    /** @var Schema */
    private $schema;

    public function setUp()
    {
        $this->interfaceType = new InterfaceType([
            'name'   => 'Interface',
            'fields' => ['fieldName' => ['type' => Type::string()]],
        ]);

        $this->implementingType = new ObjectType([
            'name'       => 'Object',
            'interfaces' => [$this->interfaceType],
            'fields'     => [
                'fieldName' => [
                    'type'    => Type::string(),
                    'resolve' => static function () {
                        return '';
                    },
                ],
            ],
        ]);

        $this->directiveInputType = new InputObjectType([
            'name'   => 'DirInput',
            'fields' => [
                'field' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $this->wrappedDirectiveInputType = new InputObjectType([
            'name'   => 'WrappedDirInput',
            'fields' => [
                'field' => [
                    'type' => Type::string(),
                ],
            ],
        ]);

        $this->directive = new Directive([
            'name'      => 'dir',
            'locations' => ['OBJECT'],
            'args'      => [
                'arg'     => [
                    'type' => $this->directiveInputType,
                ],
                'argList' => [
                    'type' => Type::listOf($this->wrappedDirectiveInputType),
                ],
            ],
        ]);

        $this->schema = new Schema([
            'query'      => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'getObject' => [
                        'type'    => $this->interfaceType,
                        'resolve' => static function () {
                            return [];
                        },
                    ],
                ],
            ]),
            'directives' => [$this->directive],
        ]);
    }

    // Type System: Schema
    // Getting possible types

    /**
     * @see it('throws human-reable error if schema.types is not defined')
     */
    public function testThrowsHumanReableErrorIfSchemaTypesIsNotDefined() : void
    {
        self::markTestSkipped("Can't check interface implementations without full schema scan");

        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage(
            'Could not find possible implementing types for Interface in schema. ' .
            'Check that schema.types is defined and is an array of all possible ' .
            'types in the schema.'
        );
        $this->schema->isPossibleType($this->interfaceType, $this->implementingType);
    }

    // Type Map

    /**
     * @see it('includes input types only used in directives')
     */
    public function testIncludesInputTypesOnlyUsedInDirectives() : void
    {
        $typeMap = $this->schema->getTypeMap();
        self::assertArrayHasKey('DirInput', $typeMap);
        self::assertArrayHasKey('WrappedDirInput', $typeMap);
    }
}
