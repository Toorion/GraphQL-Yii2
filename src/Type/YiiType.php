<?php

declare(strict_types=1);

namespace YiiGraphQL\Type;

use YiiGraphQL\Type\Definition\Type;

class YiiType
{
    public static function cast( $dbTypeName )
    {
        switch($dbTypeName) {
            case "integer":
            case "int":
            case "smallint":
            case "bigint":
                return Type::int();
            case "string":
            case "safe":
            case "text":
            case "json":
                return Type::string();
            case "boolean":
                return Type::boolean();
            case "double":
            case "number":
                return Type::float();
            case "array":
                return Type::hash();
        }

        return null;
    }
}