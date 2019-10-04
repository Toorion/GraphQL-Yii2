<?php

declare(strict_types=1);

namespace YiiGraphQL\Type;

use YiiGraphQL\Type\Definition\Type;

class YiiType
{
    public static function cast( $typeName )
    {
        $required = false;
        if(substr($typeName, -1) == '!') {
            $required = true;
            $typeName = substr($typeName, 0, -1);
        }

        $type = null;
        switch($typeName) {
            case "integer":
            case "int":
            case "smallint":
            case "bigint":
                $type = Type::int();
                break;
            case "string":
            case "safe":
            case "text":
                $type = Type::string();
                break;
            case "json":
                $type = Type::hash();
                break;
            case "boolean":
                $type = Type::boolean();
                break;
            case "double":
            case "number":
            case "decimal":
                $type = Type::float();
                break;
            case "array":
                $type = Type::hash();
                break;
        }

        if($required) {
            return Type::nonNull($type);
        }

        return $type;
    }
}
