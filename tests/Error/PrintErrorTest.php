<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests\Error;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Error\FormattedError;
use YiiGraphQL\Language\Parser;
use YiiGraphQL\Language\Source;
use YiiGraphQL\Language\SourceLocation;
use PHPUnit\Framework\TestCase;

class PrintErrorTest extends TestCase
{
    /**
     * @see it('prints an line numbers with correct padding')
     */
    public function testPrintsAnLineNumbersWithCorrectPadding() : void
    {
        $singleDigit = new Error(
            'Single digit line number with no padding',
            null,
            new Source('*', 'Test', new SourceLocation(9, 1)),
            [0]
        );

        $actual   = FormattedError::printError($singleDigit);
        $expected = 'Single digit line number with no padding

Test (9:1)
 9: *
    ^
';
        self::assertEquals($expected, $actual);

        $doubleDigit = new Error(
            'Left padded first line number',
            null,
            new Source("*\n", 'Test', new SourceLocation(9, 1)),
            [0]
        );
        $actual      = FormattedError::printError($doubleDigit);
        $expected    = 'Left padded first line number

Test (9:1)
 9: *
    ^
10: 
';
        self::assertEquals($expected, $actual);
    }

    /**
     * @see it('prints an error with nodes from different sources')
     */
    public function testPrintsAnErrorWithNodesFromDifferentSources() : void
    {
        $sourceA = Parser::parse(new Source(
            'type Foo {
  field: String
}',
            'SourceA'
        ));

        $fieldTypeA = $sourceA->definitions[0]->fields[0]->type;

        $sourceB = Parser::parse(new Source(
            'type Foo {
  field: Int
}',
            'SourceB'
        ));

        $fieldTypeB = $sourceB->definitions[0]->fields[0]->type;

        $error = new Error(
            'Example error with two nodes',
            [
                $fieldTypeA,
                $fieldTypeB,
            ]
        );

        self::assertEquals(
            'Example error with two nodes

SourceA (2:10)
1: type Foo {
2:   field: String
            ^
3: }

SourceB (2:10)
1: type Foo {
2:   field: Int
            ^
3: }
',
            FormattedError::printError($error)
        );
    }
}
