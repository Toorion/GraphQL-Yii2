<?php
namespace YiiGraphQL\Examples\Blog\Type;

use YiiGraphQL\Examples\Blog\Data\Story;
use YiiGraphQL\Examples\Blog\Data\User;
use YiiGraphQL\Examples\Blog\Types;
use YiiGraphQL\Type\Definition\UnionType;

class SearchResultType extends UnionType
{
    public function __construct()
    {
        $config = [
            'name' => 'SearchResultType',
            'types' => function() {
                return [
                    Types::story(),
                    Types::user()
                ];
            },
            'resolveType' => function($value) {
                if ($value instanceof Story) {
                    return Types::story();
                } else if ($value instanceof User) {
                    return Types::user();
                }
            }
        ];
        parent::__construct($config);
    }
}
