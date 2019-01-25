<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests\Type\TestClasses;

use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\Type;

/**
 * Note: named OtherCustom vs OtherCustomType intentionally
 */
class OtherCustom extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'b' => Type::string(),
            ],
        ];
        parent::__construct($config);
    }
}
