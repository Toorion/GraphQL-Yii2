<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests;

use YiiGraphQL\Executor\Executor;
use YiiGraphQL\Experimental\Executor\CoroutineExecutor;
use function getenv;

require_once __DIR__ . '/../vendor/autoload.php';

if (getenv('EXECUTOR') === 'coroutine') {
    Executor::setImplementationFactory([CoroutineExecutor::class, 'create']);
}
