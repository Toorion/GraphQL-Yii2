<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests\Validator;

use YiiGraphQL\Error\FormattedError;
use YiiGraphQL\Language\SourceLocation;
use YiiGraphQL\Validator\Rules\KnownDirectives;

class KnownDirectivesTest extends ValidatorTestCase
{
    // Validate: Known directives
    /**
     * @see it('with no directives')
     */
    public function testWithNoDirectives() : void
    {
        $this->expectPassesRule(
            new KnownDirectives(),
            '
      query Foo {
        name
        ...Frag
      }

      fragment Frag on Dog {
        name
      }
        '
        );
    }

    /**
     * @see it('with known directives')
     */
    public function testWithKnownDirectives() : void
    {
        $this->expectPassesRule(
            new KnownDirectives(),
            '
      {
        dog @include(if: true) {
          name
        }
        human @skip(if: true) {
          name
        }
      }
        '
        );
    }

    /**
     * @see it('with unknown directive')
     */
    public function testWithUnknownDirective() : void
    {
        $this->expectFailsRule(
            new KnownDirectives(),
            '
      {
        dog @unknown(directive: "value") {
          name
        }
      }
        ',
            [$this->unknownDirective('unknown', 3, 13)]
        );
    }

    private function unknownDirective($directiveName, $line, $column)
    {
        return FormattedError::create(
            KnownDirectives::unknownDirectiveMessage($directiveName),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('with many unknown directives')
     */
    public function testWithManyUnknownDirectives() : void
    {
        $this->expectFailsRule(
            new KnownDirectives(),
            '
      {
        dog @unknown(directive: "value") {
          name
        }
        human @unknown(directive: "value") {
          name
          pets @unknown(directive: "value") {
            name
          }
        }
      }
        ',
            [
                $this->unknownDirective('unknown', 3, 13),
                $this->unknownDirective('unknown', 6, 15),
                $this->unknownDirective('unknown', 8, 16),
            ]
        );
    }

    /**
     * @see it('with well placed directives')
     */
    public function testWithWellPlacedDirectives() : void
    {
        $this->expectPassesRule(
            new KnownDirectives(),
            '
      query Foo @onQuery {
        name @include(if: true)
        ...Frag @include(if: true)
        skippedField @skip(if: true)
        ...SkippedFrag @skip(if: true)
      }
      
      mutation Bar @onMutation {
        someField
      }
        '
        );
    }

    // within schema language

    /**
     * @see it('with misplaced directives')
     */
    public function testWithMisplacedDirectives() : void
    {
        $this->expectFailsRule(
            new KnownDirectives(),
            '
      query Foo @include(if: true) {
        name @onQuery
        ...Frag @onQuery
      }

      mutation Bar @onQuery {
        someField
      }
        ',
            [
                $this->misplacedDirective('include', 'QUERY', 2, 17),
                $this->misplacedDirective('onQuery', 'FIELD', 3, 14),
                $this->misplacedDirective('onQuery', 'FRAGMENT_SPREAD', 4, 17),
                $this->misplacedDirective('onQuery', 'MUTATION', 7, 20),
            ]
        );
    }

    private function misplacedDirective($directiveName, $placement, $line, $column)
    {
        return FormattedError::create(
            KnownDirectives::misplacedDirectiveMessage($directiveName, $placement),
            [new SourceLocation($line, $column)]
        );
    }

    /**
     * @see it('with well placed directives')
     */
    public function testWSLWithWellPlacedDirectives() : void
    {
        $this->expectPassesRule(
            new KnownDirectives(),
            '
        type MyObj implements MyInterface @onObject {
          myField(myArg: Int @onArgumentDefinition): String @onFieldDefinition
        }

        extend type MyObj @onObject

        scalar MyScalar @onScalar
        
        extend scalar MyScalar @onScalar

        interface MyInterface @onInterface {
          myField(myArg: Int @onArgumentDefinition): String @onFieldDefinition
        }
        
        extend interface MyInterface @onInterface

        union MyUnion @onUnion = MyObj | Other
        
        extend union MyUnion @onUnion

        enum MyEnum @onEnum {
          MY_VALUE @onEnumValue
        }
        
        extend enum MyEnum @onEnum

        input MyInput @onInputObject {
          myField: Int @onInputFieldDefinition
        }
        
        extend input MyInput @onInputObject

        schema @onSchema {
          query: MyQuery
        }
        '
        );
    }

    /**
     * @see it('with misplaced directives')
     */
    public function testWSLWithMisplacedDirectives() : void
    {
        $this->expectFailsRule(
            new KnownDirectives(),
            '
        type MyObj implements MyInterface @onInterface {
          myField(myArg: Int @onInputFieldDefinition): String @onInputFieldDefinition
        }

        scalar MyScalar @onEnum

        interface MyInterface @onObject {
          myField(myArg: Int @onInputFieldDefinition): String @onInputFieldDefinition
        }

        union MyUnion @onEnumValue = MyObj | Other

        enum MyEnum @onScalar {
          MY_VALUE @onUnion
        }

        input MyInput @onEnum {
          myField: Int @onArgumentDefinition
        }

        schema @onObject {
          query: MyQuery
        }
        ',
            [
                $this->misplacedDirective('onInterface', 'OBJECT', 2, 43),
                $this->misplacedDirective('onInputFieldDefinition', 'ARGUMENT_DEFINITION', 3, 30),
                $this->misplacedDirective('onInputFieldDefinition', 'FIELD_DEFINITION', 3, 63),
                $this->misplacedDirective('onEnum', 'SCALAR', 6, 25),
                $this->misplacedDirective('onObject', 'INTERFACE', 8, 31),
                $this->misplacedDirective('onInputFieldDefinition', 'ARGUMENT_DEFINITION', 9, 30),
                $this->misplacedDirective('onInputFieldDefinition', 'FIELD_DEFINITION', 9, 63),
                $this->misplacedDirective('onEnumValue', 'UNION', 12, 23),
                $this->misplacedDirective('onScalar', 'ENUM', 14, 21),
                $this->misplacedDirective('onUnion', 'ENUM_VALUE', 15, 20),
                $this->misplacedDirective('onEnum', 'INPUT_OBJECT', 18, 23),
                $this->misplacedDirective('onArgumentDefinition', 'INPUT_FIELD_DEFINITION', 19, 24),
                $this->misplacedDirective('onObject', 'SCHEMA', 22, 16),
            ]
        );
    }
}
