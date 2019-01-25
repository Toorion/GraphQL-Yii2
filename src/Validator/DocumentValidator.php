<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator;

use Exception;
use YiiGraphQL\Error\Error;
use YiiGraphQL\Language\AST\DocumentNode;
use YiiGraphQL\Language\Visitor;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Type\Schema;
use YiiGraphQL\Utils\TypeInfo;
use YiiGraphQL\Validator\Rules\DisableIntrospection;
use YiiGraphQL\Validator\Rules\ExecutableDefinitions;
use YiiGraphQL\Validator\Rules\FieldsOnCorrectType;
use YiiGraphQL\Validator\Rules\FragmentsOnCompositeTypes;
use YiiGraphQL\Validator\Rules\KnownArgumentNames;
use YiiGraphQL\Validator\Rules\KnownArgumentNamesOnDirectives;
use YiiGraphQL\Validator\Rules\KnownDirectives;
use YiiGraphQL\Validator\Rules\KnownFragmentNames;
use YiiGraphQL\Validator\Rules\KnownTypeNames;
use YiiGraphQL\Validator\Rules\LoneAnonymousOperation;
use YiiGraphQL\Validator\Rules\LoneSchemaDefinition;
use YiiGraphQL\Validator\Rules\NoFragmentCycles;
use YiiGraphQL\Validator\Rules\NoUndefinedVariables;
use YiiGraphQL\Validator\Rules\NoUnusedFragments;
use YiiGraphQL\Validator\Rules\NoUnusedVariables;
use YiiGraphQL\Validator\Rules\OverlappingFieldsCanBeMerged;
use YiiGraphQL\Validator\Rules\PossibleFragmentSpreads;
use YiiGraphQL\Validator\Rules\ProvidedNonNullArguments;
use YiiGraphQL\Validator\Rules\ProvidedRequiredArgumentsOnDirectives;
use YiiGraphQL\Validator\Rules\QueryComplexity;
use YiiGraphQL\Validator\Rules\QueryDepth;
use YiiGraphQL\Validator\Rules\QuerySecurityRule;
use YiiGraphQL\Validator\Rules\ScalarLeafs;
use YiiGraphQL\Validator\Rules\UniqueArgumentNames;
use YiiGraphQL\Validator\Rules\UniqueDirectivesPerLocation;
use YiiGraphQL\Validator\Rules\UniqueFragmentNames;
use YiiGraphQL\Validator\Rules\UniqueInputFieldNames;
use YiiGraphQL\Validator\Rules\UniqueOperationNames;
use YiiGraphQL\Validator\Rules\UniqueVariableNames;
use YiiGraphQL\Validator\Rules\ValidationRule;
use YiiGraphQL\Validator\Rules\ValuesOfCorrectType;
use YiiGraphQL\Validator\Rules\VariablesAreInputTypes;
use YiiGraphQL\Validator\Rules\VariablesDefaultValueAllowed;
use YiiGraphQL\Validator\Rules\VariablesInAllowedPosition;
use Throwable;
use function array_filter;
use function array_map;
use function array_merge;
use function count;
use function implode;
use function is_array;
use function sprintf;

/**
 * Implements the "Validation" section of the spec.
 *
 * Validation runs synchronously, returning an array of encountered errors, or
 * an empty array if no errors were encountered and the document is valid.
 *
 * A list of specific validation rules may be provided. If not provided, the
 * default list of rules defined by the GraphQL specification will be used.
 *
 * Each validation rule is an instance of GraphQL\Validator\Rules\ValidationRule
 * which returns a visitor (see the [GraphQL\Language\Visitor API](reference.md#graphqllanguagevisitor)).
 *
 * Visitor methods are expected to return an instance of [GraphQL\Error\Error](reference.md#graphqlerrorerror),
 * or array of such instances when invalid.
 *
 * Optionally a custom TypeInfo instance may be provided. If not provided, one
 * will be created from the provided schema.
 */
class DocumentValidator
{
    /** @var ValidationRule[] */
    private static $rules = [];

    /** @var ValidationRule[]|null */
    private static $defaultRules;

    /** @var QuerySecurityRule[]|null */
    private static $securityRules;

    /** @var ValidationRule[]|null */
    private static $sdlRules;

    /** @var bool */
    private static $initRules = false;

    /**
     * Primary method for query validation. See class description for details.
     *
     * @param ValidationRule[]|null $rules
     *
     * @return Error[]
     *
     * @api
     */
    public static function validate(
        Schema $schema,
        DocumentNode $ast,
        ?array $rules = null,
        ?TypeInfo $typeInfo = null
    ) {
        if ($rules === null) {
            $rules = static::allRules();
        }

        if (is_array($rules) === true && count($rules) === 0) {
            // Skip validation if there are no rules
            return [];
        }

        $typeInfo = $typeInfo ?: new TypeInfo($schema);

        return static::visitUsingRules($schema, $typeInfo, $ast, $rules);
    }

    /**
     * Returns all global validation rules.
     *
     * @return ValidationRule[]
     *
     * @api
     */
    public static function allRules()
    {
        if (! self::$initRules) {
            static::$rules     = array_merge(static::defaultRules(), self::securityRules(), self::$rules);
            static::$initRules = true;
        }

        return self::$rules;
    }

    public static function defaultRules()
    {
        if (self::$defaultRules === null) {
            self::$defaultRules = [
                ExecutableDefinitions::class        => new ExecutableDefinitions(),
                UniqueOperationNames::class         => new UniqueOperationNames(),
                LoneAnonymousOperation::class       => new LoneAnonymousOperation(),
                KnownTypeNames::class               => new KnownTypeNames(),
                FragmentsOnCompositeTypes::class    => new FragmentsOnCompositeTypes(),
                VariablesAreInputTypes::class       => new VariablesAreInputTypes(),
                ScalarLeafs::class                  => new ScalarLeafs(),
                FieldsOnCorrectType::class          => new FieldsOnCorrectType(),
                UniqueFragmentNames::class          => new UniqueFragmentNames(),
                KnownFragmentNames::class           => new KnownFragmentNames(),
                NoUnusedFragments::class            => new NoUnusedFragments(),
                PossibleFragmentSpreads::class      => new PossibleFragmentSpreads(),
                NoFragmentCycles::class             => new NoFragmentCycles(),
                UniqueVariableNames::class          => new UniqueVariableNames(),
                NoUndefinedVariables::class         => new NoUndefinedVariables(),
                NoUnusedVariables::class            => new NoUnusedVariables(),
                KnownDirectives::class              => new KnownDirectives(),
                UniqueDirectivesPerLocation::class  => new UniqueDirectivesPerLocation(),
                KnownArgumentNames::class           => new KnownArgumentNames(),
                UniqueArgumentNames::class          => new UniqueArgumentNames(),
                ValuesOfCorrectType::class          => new ValuesOfCorrectType(),
                ProvidedNonNullArguments::class     => new ProvidedNonNullArguments(),
                VariablesDefaultValueAllowed::class => new VariablesDefaultValueAllowed(),
                VariablesInAllowedPosition::class   => new VariablesInAllowedPosition(),
                OverlappingFieldsCanBeMerged::class => new OverlappingFieldsCanBeMerged(),
                UniqueInputFieldNames::class        => new UniqueInputFieldNames(),
            ];
        }

        return self::$defaultRules;
    }

    /**
     * @return QuerySecurityRule[]
     */
    public static function securityRules()
    {
        // This way of defining rules is deprecated
        // When custom security rule is required - it should be just added via DocumentValidator::addRule();
        // TODO: deprecate this

        if (self::$securityRules === null) {
            self::$securityRules = [
                DisableIntrospection::class => new DisableIntrospection(DisableIntrospection::DISABLED), // DEFAULT DISABLED
                QueryDepth::class           => new QueryDepth(QueryDepth::DISABLED), // default disabled
                QueryComplexity::class      => new QueryComplexity(QueryComplexity::DISABLED), // default disabled
            ];
        }

        return self::$securityRules;
    }

    public static function sdlRules()
    {
        if (self::$sdlRules === null) {
            self::$sdlRules = [
                LoneSchemaDefinition::class                  => new LoneSchemaDefinition(),
                KnownDirectives::class                       => new KnownDirectives(),
                KnownArgumentNamesOnDirectives::class        => new KnownArgumentNamesOnDirectives(),
                UniqueDirectivesPerLocation::class           => new UniqueDirectivesPerLocation(),
                UniqueArgumentNames::class                   => new UniqueArgumentNames(),
                UniqueInputFieldNames::class                 => new UniqueInputFieldNames(),
                ProvidedRequiredArgumentsOnDirectives::class => new ProvidedRequiredArgumentsOnDirectives(),
            ];
        }

        return self::$sdlRules;
    }

    /**
     * This uses a specialized visitor which runs multiple visitors in parallel,
     * while maintaining the visitor skip and break API.
     *
     * @param ValidationRule[] $rules
     *
     * @return Error[]
     */
    public static function visitUsingRules(Schema $schema, TypeInfo $typeInfo, DocumentNode $documentNode, array $rules)
    {
        $context  = new ValidationContext($schema, $documentNode, $typeInfo);
        $visitors = [];
        foreach ($rules as $rule) {
            $visitors[] = $rule->getVisitor($context);
        }
        Visitor::visit($documentNode, Visitor::visitWithTypeInfo($typeInfo, Visitor::visitInParallel($visitors)));

        return $context->getErrors();
    }

    /**
     * Returns global validation rule by name. Standard rules are named by class name, so
     * example usage for such rules:
     *
     * $rule = DocumentValidator::getRule(GraphQL\Validator\Rules\QueryComplexity::class);
     *
     * @param string $name
     *
     * @return ValidationRule
     *
     * @api
     */
    public static function getRule($name)
    {
        $rules = static::allRules();

        if (isset($rules[$name])) {
            return $rules[$name];
        }

        $name = sprintf('GraphQL\\Validator\\Rules\\%s', $name);

        return $rules[$name] ?? null;
    }

    /**
     * Add rule to list of global validation rules
     *
     * @api
     */
    public static function addRule(ValidationRule $rule)
    {
        self::$rules[$rule->getName()] = $rule;
    }

    public static function isError($value)
    {
        return is_array($value)
            ? count(array_filter(
                $value,
                static function ($item) {
                    return $item instanceof Exception || $item instanceof Throwable;
                }
            )) === count($value)
            : ($value instanceof Exception || $value instanceof Throwable);
    }

    public static function append(&$arr, $items)
    {
        if (is_array($items)) {
            $arr = array_merge($arr, $items);
        } else {
            $arr[] = $items;
        }

        return $arr;
    }

    /**
     * Utility which determines if a value literal node is valid for an input type.
     *
     * Deprecated. Rely on validation for documents co
     * ntaining literal values.
     *
     * @deprecated
     *
     * @return Error[]
     */
    public static function isValidLiteralValue(Type $type, $valueNode)
    {
        $emptySchema = new Schema([]);
        $emptyDoc    = new DocumentNode(['definitions' => []]);
        $typeInfo    = new TypeInfo($emptySchema, $type);
        $context     = new ValidationContext($emptySchema, $emptyDoc, $typeInfo);
        $validator   = new ValuesOfCorrectType();
        $visitor     = $validator->getVisitor($context);
        Visitor::visit($valueNode, Visitor::visitWithTypeInfo($typeInfo, $visitor));

        return $context->getErrors();
    }

    public static function assertValidSDLExtension(DocumentNode $documentAST, Schema $schema)
    {
        $errors = self::visitUsingRules($schema, new TypeInfo($schema), $documentAST, self::sdlRules());
        if (count($errors) !== 0) {
            throw new Error(
                implode(
                    "\n\n",
                    array_map(static function (Error $error) : string {
                        return $error->message;
                    }, $errors)
                )
            );
        }
    }
}
