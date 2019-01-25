<?php

declare(strict_types=1);

namespace YiiGraphQL\Executor;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Executor\Promise\PromiseAdapter;
use YiiGraphQL\Language\AST\FragmentDefinitionNode;
use YiiGraphQL\Language\AST\OperationDefinitionNode;
use YiiGraphQL\QueryModel;
use YiiGraphQL\Type\Schema;

/**
 * Data that must be available at all points during query execution.
 *
 * Namely, schema of the type system that is currently executing,
 * and the fragments defined in the query document
 *
 * @internal
 */
class ExecutionContext
{
    /** @var QueryModel */
    public $queryModel;

    /** @var FragmentDefinitionNode[] */
    public $fragments;

    /** @var mixed */
    public $rootValue;

    /** @var mixed */
    public $contextValue;

    /** @var OperationDefinitionNode */
    public $operation;

    /** @var mixed[] */
    public $variableValues;

    /** @var callable */
    public $fieldResolver;

    /** @var Error[] */
    public $errors;

    /** @var PromiseAdapter */
    public $promises;

    public function __construct(
        $queryModel,
        $fragments,
        $root,
        $contextValue,
        $operation,
        $variables,
        $errors,
        $fieldResolver,
        $promiseAdapter
    ) {
        $this->queryModel     = $queryModel;
        $this->fragments      = $fragments;
        $this->rootValue      = $root;
        $this->contextValue   = $contextValue;
        $this->operation      = $operation;
        $this->variableValues = $variables;
        $this->errors         = $errors ?: [];
        $this->fieldResolver  = $fieldResolver;
        $this->promises       = $promiseAdapter;
    }

    public function addError(Error $error)
    {
        $this->errors[] = $error;

        return $this;
    }
}
