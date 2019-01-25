<?php

declare(strict_types=1);

namespace YiiGraphQL\Executor;

use YiiGraphQL\Executor\Promise\Promise;

interface ExecutorImplementation
{
    /**
     * Returns promise of {@link ExecutionResult}. Promise should always resolve, never reject.
     */
    public function doExecute() : Promise;
}
