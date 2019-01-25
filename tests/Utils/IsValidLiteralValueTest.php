<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests\Utils;

use YiiGraphQL\Language\Parser;
use YiiGraphQL\Language\SourceLocation;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Validator\DocumentValidator;
use PHPUnit\Framework\TestCase;

class IsValidLiteralValueTest extends TestCase
{
    // DESCRIBE: isValidLiteralValue
    /**
     * @see it('Returns no errors for a valid value')
     */
    public function testReturnsNoErrorsForAValidValue() : void
    {
        self::assertEquals(
            [],
            DocumentValidator::isValidLiteralValue(Type::int(), Parser::parseValue('123'))
        );
    }

    /**
     * @see it('Returns errors for an invalid value')
     */
    public function testReturnsErrorsForForInvalidValue() : void
    {
        $errors = DocumentValidator::isValidLiteralValue(Type::int(), Parser::parseValue('"abc"'));

        self::assertCount(1, $errors);
        self::assertEquals('Expected type Int, found "abc".', $errors[0]->getMessage());
        self::assertEquals([new SourceLocation(1, 1)], $errors[0]->getLocations());
        self::assertEquals(null, $errors[0]->getPath());
    }
}
