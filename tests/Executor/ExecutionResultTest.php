<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests\Executor;

use YiiGraphQL\Executor\ExecutionResult;
use PHPUnit\Framework\TestCase;

class ExecutionResultTest extends TestCase
{
    public function testToArrayWithoutExtensions() : void
    {
        $executionResult = new ExecutionResult();

        self::assertEquals([], $executionResult->toArray());
    }

    public function testToArrayExtensions() : void
    {
        $executionResult = new ExecutionResult(null, [], ['foo' => 'bar']);

        self::assertEquals(['extensions' => ['foo' => 'bar']], $executionResult->toArray());

        $executionResult->extensions = ['bar' => 'foo'];

        self::assertEquals(['extensions' => ['bar' => 'foo']], $executionResult->toArray());
    }
}
