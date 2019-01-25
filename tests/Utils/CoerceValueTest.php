<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests\Utils;

use YiiGraphQL\Type\Definition\EnumType;
use YiiGraphQL\Type\Definition\InputObjectType;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Utils\Utils;
use YiiGraphQL\Utils\Value;
use PHPUnit\Framework\TestCase;

class CoerceValueTest extends TestCase
{
    /** @var EnumType */
    private $testEnum;

    /** @var InputObjectType */
    private $testInputObject;

    public function setUp()
    {
        $this->testEnum = new EnumType([
            'name'   => 'TestEnum',
            'values' => [
                'FOO' => 'InternalFoo',
                'BAR' => 123456789,
            ],
        ]);

        $this->testInputObject = new InputObjectType([
            'name'   => 'TestInputObject',
            'fields' => [
                'foo' => Type::nonNull(Type::int()),
                'bar' => Type::int(),
            ],
        ]);
    }

    /**
     * Describe: coerceValue
     *
     * @see it('coercing an array to GraphQLString produces an error')
     */
    public function testCoercingAnArrayToGraphQLStringProducesAnError() : void
    {
        $result = Value::coerceValue([1, 2, 3], Type::string());
        $this->expectError(
            $result,
            'Expected type String; String cannot represent an array value: [1,2,3]'
        );

        self::assertEquals(
            'String cannot represent an array value: [1,2,3]',
            $result['errors'][0]->getPrevious()->getMessage()
        );
    }

    /**
     * Describe: for GraphQLInt
     */
    private function expectError($result, $expected)
    {
        self::assertInternalType('array', $result);
        self::assertInternalType('array', $result['errors']);
        self::assertCount(1, $result['errors']);
        self::assertEquals($expected, $result['errors'][0]->getMessage());
        self::assertEquals(Utils::undefined(), $result['value']);
    }

    /**
     * @see it('returns no error for int input')
     */
    public function testIntReturnsNoErrorForIntInput() : void
    {
        $result = Value::coerceValue('1', Type::int());
        $this->expectNoErrors($result);
    }

    private function expectNoErrors($result)
    {
        self::assertInternalType('array', $result);
        self::assertNull($result['errors']);
        self::assertNotEquals(Utils::undefined(), $result['value']);
    }

    /**
     * @see it('returns no error for negative int input')
     */
    public function testIntReturnsNoErrorForNegativeIntInput() : void
    {
        $result = Value::coerceValue('-1', Type::int());
        $this->expectNoErrors($result);
    }

    /**
     * @see it('returns no error for exponent input')
     */
    public function testIntReturnsNoErrorForExponentInput() : void
    {
        $result = Value::coerceValue('1e3', Type::int());
        $this->expectNoErrors($result);
    }

    /**
     * @see it('returns no error for null')
     */
    public function testIntReturnsASingleErrorNull() : void
    {
        $result = Value::coerceValue(null, Type::int());
        $this->expectNoErrors($result);
    }

    /**
     * @see it('returns a single error for empty value')
     */
    public function testIntReturnsASingleErrorForEmptyValue() : void
    {
        $result = Value::coerceValue('', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non 32-bit signed integer value: (empty string)'
        );
    }

    /**
     * @see it('returns error for float input as int')
     */
    public function testIntReturnsErrorForFloatInputAsInt() : void
    {
        $result = Value::coerceValue('1.5', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non-integer value: 1.5'
        );
    }

    // Describe: for GraphQLFloat

    /**
     * @see it('returns a single error for char input')
     */
    public function testIntReturnsASingleErrorForCharInput() : void
    {
        $result = Value::coerceValue('a', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non 32-bit signed integer value: a'
        );
    }

    /**
     * @see it('returns a single error for multi char input')
     */
    public function testIntReturnsASingleErrorForMultiCharInput() : void
    {
        $result = Value::coerceValue('meow', Type::int());
        $this->expectError(
            $result,
            'Expected type Int; Int cannot represent non 32-bit signed integer value: meow'
        );
    }

    /**
     * @see it('returns no error for int input')
     */
    public function testFloatReturnsNoErrorForIntInput() : void
    {
        $result = Value::coerceValue('1', Type::float());
        $this->expectNoErrors($result);
    }

    /**
     * @see it('returns no error for exponent input')
     */
    public function testFloatReturnsNoErrorForExponentInput() : void
    {
        $result = Value::coerceValue('1e3', Type::float());
        $this->expectNoErrors($result);
    }

    /**
     * @see it('returns no error for float input')
     */
    public function testFloatReturnsNoErrorForFloatInput() : void
    {
        $result = Value::coerceValue('1.5', Type::float());
        $this->expectNoErrors($result);
    }

    /**
     * @see it('returns no error for null')
     */
    public function testFloatReturnsASingleErrorNull() : void
    {
        $result = Value::coerceValue(null, Type::float());
        $this->expectNoErrors($result);
    }

    /**
     * @see it('returns a single error for empty value')
     */
    public function testFloatReturnsASingleErrorForEmptyValue() : void
    {
        $result = Value::coerceValue('', Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: (empty string)'
        );
    }

    // DESCRIBE: for GraphQLEnum

    /**
     * @see it('returns a single error for char input')
     */
    public function testFloatReturnsASingleErrorForCharInput() : void
    {
        $result = Value::coerceValue('a', Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: a'
        );
    }

    /**
     * @see it('returns a single error for multi char input')
     */
    public function testFloatReturnsASingleErrorForMultiCharInput() : void
    {
        $result = Value::coerceValue('meow', Type::float());
        $this->expectError(
            $result,
            'Expected type Float; Float cannot represent non numeric value: meow'
        );
    }

    /**
     * @see it('returns no error for a known enum name')
     */
    public function testReturnsNoErrorForAKnownEnumName() : void
    {
        $fooResult = Value::coerceValue('FOO', $this->testEnum);
        $this->expectNoErrors($fooResult);
        self::assertEquals('InternalFoo', $fooResult['value']);

        $barResult = Value::coerceValue('BAR', $this->testEnum);
        $this->expectNoErrors($barResult);
        self::assertEquals(123456789, $barResult['value']);
    }

    // DESCRIBE: for GraphQLInputObject

    /**
     * @see it('results error for misspelled enum value')
     */
    public function testReturnsErrorForMisspelledEnumValue() : void
    {
        $result = Value::coerceValue('foo', $this->testEnum);
        $this->expectError($result, 'Expected type TestEnum; did you mean FOO?');
    }

    /**
     * @see it('results error for incorrect value type')
     */
    public function testReturnsErrorForIncorrectValueType() : void
    {
        $result1 = Value::coerceValue(123, $this->testEnum);
        $this->expectError($result1, 'Expected type TestEnum.');

        $result2 = Value::coerceValue(['field' => 'value'], $this->testEnum);
        $this->expectError($result2, 'Expected type TestEnum.');
    }

    /**
     * @see it('returns no error for a valid input')
     */
    public function testReturnsNoErrorForValidInput() : void
    {
        $result = Value::coerceValue(['foo' => 123], $this->testInputObject);
        $this->expectNoErrors($result);
        self::assertEquals(['foo' => 123], $result['value']);
    }

    /**
     * @see it('returns no error for a non-object type')
     */
    public function testReturnsErrorForNonObjectType() : void
    {
        $result = Value::coerceValue(123, $this->testInputObject);
        $this->expectError($result, 'Expected type TestInputObject to be an object.');
    }

    /**
     * @see it('returns no error for an invalid field')
     */
    public function testReturnErrorForAnInvalidField() : void
    {
        $result = Value::coerceValue(['foo' => 'abc'], $this->testInputObject);
        $this->expectError(
            $result,
            'Expected type Int at value.foo; Int cannot represent non 32-bit signed integer value: abc'
        );
    }

    /**
     * @see it('returns multiple errors for multiple invalid fields')
     */
    public function testReturnsMultipleErrorsForMultipleInvalidFields() : void
    {
        $result = Value::coerceValue(['foo' => 'abc', 'bar' => 'def'], $this->testInputObject);
        self::assertEquals(
            [
                'Expected type Int at value.foo; Int cannot represent non 32-bit signed integer value: abc',
                'Expected type Int at value.bar; Int cannot represent non 32-bit signed integer value: def',
            ],
            $result['errors']
        );
    }

    /**
     * @see it('returns error for a missing required field')
     */
    public function testReturnsErrorForAMissingRequiredField() : void
    {
        $result = Value::coerceValue(['bar' => 123], $this->testInputObject);
        $this->expectError($result, 'Field value.foo of required type Int! was not provided.');
    }

    /**
     * @see it('returns error for an unknown field')
     */
    public function testReturnsErrorForAnUnknownField() : void
    {
        $result = Value::coerceValue(['foo' => 123, 'unknownField' => 123], $this->testInputObject);
        $this->expectError($result, 'Field "unknownField" is not defined by type TestInputObject.');
    }

    /**
     * @see it('returns error for a misspelled field')
     */
    public function testReturnsErrorForAMisspelledField() : void
    {
        $result = Value::coerceValue(['foo' => 123, 'bart' => 123], $this->testInputObject);
        $this->expectError($result, 'Field "bart" is not defined by type TestInputObject; did you mean bar?');
    }
}
