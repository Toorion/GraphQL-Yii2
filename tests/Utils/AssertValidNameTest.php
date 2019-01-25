<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests\Utils;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Error\InvariantViolation;
use YiiGraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

class AssertValidNameTest extends TestCase
{
    // Describe: assertValidName()
    /**
     * @see it('throws for use of leading double underscores')
     */
    public function testThrowsForUseOfLeadingDoubleUnderscores() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('"__bad" must not begin with "__", which is reserved by GraphQL introspection.');
        Utils::assertValidName('__bad');
    }

    /**
     * @see it('throws for non-strings')
     */
    public function testThrowsForNonStrings() : void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('Expected string');
        Utils::assertValidName([]);
    }

    /**
     * @see it('throws for names with invalid characters')
     */
    public function testThrowsForNamesWithInvalidCharacters() : void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Names must match');
        Utils::assertValidName('>--()-->');
    }
}
