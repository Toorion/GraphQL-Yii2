<?php

declare(strict_types=1);

namespace YiiGraphQL\Tests\Type\TestClasses;

use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\Type;

class MyCustomType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'fields' => [
                'a' => Type::string(),
            ],
        ];
        parent::__construct($config);
    }
}
