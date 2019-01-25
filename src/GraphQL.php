<?php

declare(strict_types=1);

namespace YiiGraphQL;

use yii\base\Component;
use YiiGraphQL\Error\Error;
use YiiGraphQL\Executor\ExecutionResult;
use YiiGraphQL\Executor\Executor;
use YiiGraphQL\Executor\Promise\Adapter\SyncPromiseAdapter;
use YiiGraphQL\Executor\Promise\Promise;
use YiiGraphQL\Executor\Promise\PromiseAdapter;
use YiiGraphQL\Executor\ReferenceExecutor;
use YiiGraphQL\Experimental\Executor\CoroutineExecutor;
use YiiGraphQL\Language\AST\DocumentNode;
use YiiGraphQL\Language\Parser;
use YiiGraphQL\Language\Source;
use YiiGraphQL\Type\Definition\Directive;
use YiiGraphQL\Type\Definition\Type;
use YiiGraphQL\Type\Schema as SchemaType;
use YiiGraphQL\Validator\DocumentValidator;
use YiiGraphQL\Validator\Rules\QueryComplexity;
use YiiGraphQL\Validator\Rules\ValidationRule;
use function array_values;
use function trigger_error;
use const E_USER_DEPRECATED;

/**
 * This is the primary facade for fulfilling GraphQL operations.
 * See [related documentation](executing-queries.md).
 */
class GraphQL extends Component
{

    public $queryClasses = [];


    /**
     * Executes graphql query.
     *
     * More sophisticated GraphQL servers, such as those which persist queries,
     * may wish to separate the validation and execution phases to a static time
     * tooling step, and a server runtime step.
     *
     * Available options:
     *
     * schema:
     *    The GraphQL type system to use when validating and executing a query.
     * source:
     *    A GraphQL language formatted string representing the requested operation.
     * rootValue:
     *    The value provided as the first argument to resolver functions on the top
     *    level type (e.g. the query object type).
     * context:
     *    The value provided as the third argument to all resolvers.
     *    Use this to pass current session, user data, etc
     * variableValues:
     *    A mapping of variable name to runtime value to use for all variables
     *    defined in the requestString.
     * operationName:
     *    The name of the operation to use if requestString contains multiple
     *    possible operations. Can be omitted if requestString contains only
     *    one operation.
     * fieldResolver:
     *    A resolver function to use when one is not provided by the schema.
     *    If not provided, the default field resolver is used (which looks for a
     *    value on the source value with the field's name).
     * validationRules:
     *    A set of rules for query validation step. Default value is all available rules.
     *    Empty array would allow to skip query validation (may be convenient for persisted
     *    queries which are validated before persisting and assumed valid during execution)
     *
     * @param string|DocumentNode $source
     * @param mixed               $rootValue
     * @param mixed               $context
     * @param mixed[]|null        $variableValues
     * @param ValidationRule[]    $validationRules
     *
     * @api
     */
    public function executeQuery(
        $source,
        $rootValue = null,
        $context = null,
        $variableValues = null,
        ?string $operationName = null,
        ?callable $fieldResolver = null,
        ?array $validationRules = null
    ) : ExecutionResult {
        $promiseAdapter = new SyncPromiseAdapter();

        $promise = $this->promiseToExecute(
            $promiseAdapter,
            $source,
            $rootValue,
            $context,
            $variableValues,
            $operationName,
            $fieldResolver,
            $validationRules
        );

        return $promiseAdapter->wait($promise);
    }

    /**
     * Same as executeQuery(), but requires PromiseAdapter and always returns a Promise.
     * Useful for Async PHP platforms.
     *
     * @param string|DocumentNode   $source
     * @param mixed                 $rootValue
     * @param mixed                 $context
     * @param mixed[]|null          $variableValues
     * @param ValidationRule[]|null $validationRules
     *
     * @api
     */
    public function promiseToExecute(
        PromiseAdapter $promiseAdapter,
        $source,
        $rootValue = null,
        $context = null,
        $variableValues = null,
        ?string $operationName = null,
        ?callable $fieldResolver = null,
        ?array $validationRules = null
    ) : Promise {
        try {
            if ($source instanceof DocumentNode) {
                $documentNode = $source;
            } else {
                $documentNode = Parser::parse(new Source($source ?: '', 'GraphQL'));
            }

            // FIXME
            if (empty($validationRules)) {
                /** @var QueryComplexity $queryComplexity */
                $queryComplexity = DocumentValidator::getRule(QueryComplexity::class);
                $queryComplexity->setRawVariableValues($variableValues);
            } else {
                foreach ($validationRules as $rule) {
                    if (! ($rule instanceof QueryComplexity)) {
                        continue;
                    }

                    $rule->setRawVariableValues($variableValues);
                }
            }

//            $validationErrors = DocumentValidator::validate($schema, $documentNode, $validationRules);
//
//            if (! empty($validationErrors)) {
//                return $promiseAdapter->createFulfilled(
//                    new ExecutionResult(null, $validationErrors)
//                );
//            }

            return Executor::promiseToExecute(
                new QueryModel($this->queryClasses),
                $promiseAdapter,
                $documentNode,
                $rootValue,
                $context,
                $variableValues,
                $operationName,
                $fieldResolver
            );
        } catch (Error $e) {
            return $promiseAdapter->createFulfilled(
                new ExecutionResult(null, [$e])
            );
        }
    }

    /**
     * @deprecated Use executeQuery()->toArray() instead
     *
     * @param string|DocumentNode $source
     * @param mixed               $rootValue
     * @param mixed               $contextValue
     * @param mixed[]|null        $variableValues
     *
     * @return Promise|mixed[]
     */
    public static function execute(
        SchemaType $schema,
        $source,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        ?string $operationName = null
    ) {
        trigger_error(
            __METHOD__ . ' is deprecated, use YiiGraphQL::executeQuery()->toArray() as a quick replacement',
            E_USER_DEPRECATED
        );

        $promiseAdapter = Executor::getPromiseAdapter();
        $result         = self::promiseToExecute(
            $promiseAdapter,
            $schema,
            $source,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName
        );

        if ($promiseAdapter instanceof SyncPromiseAdapter) {
            $result = $promiseAdapter->wait($result)->toArray();
        } else {
            $result = $result->then(static function (ExecutionResult $r) {
                return $r->toArray();
            });
        }

        return $result;
    }

    /**
     * @deprecated renamed to executeQuery()
     *
     * @param string|DocumentNode $source
     * @param mixed               $rootValue
     * @param mixed               $contextValue
     * @param mixed[]|null        $variableValues
     *
     * @return ExecutionResult|Promise
     */
    public static function executeAndReturnResult(
        SchemaType $schema,
        $source,
        $rootValue = null,
        $contextValue = null,
        $variableValues = null,
        ?string $operationName = null
    ) {
        trigger_error(
            __METHOD__ . ' is deprecated, use YiiGraphQL::executeQuery() as a quick replacement',
            E_USER_DEPRECATED
        );

        $promiseAdapter = Executor::getPromiseAdapter();
        $result         = self::promiseToExecute(
            $promiseAdapter,
            $schema,
            $source,
            $rootValue,
            $contextValue,
            $variableValues,
            $operationName
        );

        if ($promiseAdapter instanceof SyncPromiseAdapter) {
            $result = $promiseAdapter->wait($result);
        }

        return $result;
    }

    /**
     * Returns directives defined in GraphQL spec
     *
     * @return Directive[]
     *
     * @api
     */
    public static function getStandardDirectives() : array
    {
        return array_values(Directive::getInternalDirectives());
    }

    /**
     * Returns types defined in GraphQL spec
     *
     * @return Type[]
     *
     * @api
     */
    public static function getStandardTypes() : array
    {
        return array_values(Type::getStandardTypes());
    }

    /**
     * Replaces standard types with types from this list (matching by name)
     * Standard types not listed here remain untouched.
     *
     * @param Type[] $types
     *
     * @api
     */
    public static function overrideStandardTypes(array $types)
    {
        Type::overrideStandardTypes($types);
    }

    /**
     * Returns standard validation rules implementing GraphQL spec
     *
     * @return ValidationRule[]
     *
     * @api
     */
    public static function getStandardValidationRules() : array
    {
        return array_values(DocumentValidator::defaultRules());
    }

    /**
     * Set default resolver implementation
     *
     * @api
     */
    public static function setDefaultFieldResolver(callable $fn) : void
    {
        Executor::setDefaultFieldResolver($fn);
    }

    public static function setPromiseAdapter(?PromiseAdapter $promiseAdapter = null) : void
    {
        Executor::setPromiseAdapter($promiseAdapter);
    }

    /**
     * Experimental: Switch to the new executor
     */
    public static function useExperimentalExecutor()
    {
        Executor::setImplementationFactory([CoroutineExecutor::class, 'create']);
    }

    /**
     * Experimental: Switch back to the default executor
     */
    public static function useReferenceExecutor()
    {
        Executor::setImplementationFactory([ReferenceExecutor::class, 'create']);
    }

    /**
     * Returns directives defined in GraphQL spec
     *
     * @deprecated Renamed to getStandardDirectives
     *
     * @return Directive[]
     */
    public static function getInternalDirectives() : array
    {
        return self::getStandardDirectives();
    }
}
