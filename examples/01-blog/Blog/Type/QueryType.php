<?php
namespace YiiGraphQL\Examples\Blog\Type;

use YiiGraphQL\Examples\Blog\AppContext;
use YiiGraphQL\Examples\Blog\Data\DataSource;
use YiiGraphQL\Examples\Blog\Types;
use YiiGraphQL\Type\Definition\ObjectType;
use YiiGraphQL\Type\Definition\ResolveInfo;
use YiiGraphQL\Type\Definition\Type;

class QueryType extends ObjectType
{
    public function __construct()
    {
        $config = [
            'name' => 'Query',
            'fields' => [
                'user' => [
                    'type' => Types::user(),
                    'description' => 'Returns user by id (in range of 1-5)',
                    'args' => [
                        'id' => Types::nonNull(Types::id())
                    ]
                ],
                'viewer' => [
                    'type' => Types::user(),
                    'description' => 'Represents currently logged-in user (for the sake of example - simply returns user with id == 1)'
                ],
                'stories' => [
                    'type' => Types::listOf(Types::story()),
                    'description' => 'Returns subset of stories posted for this blog',
                    'args' => [
                        'after' => [
                            'type' => Types::id(),
                            'description' => 'Fetch stories listed after the story with this ID'
                        ],
                        'limit' => [
                            'type' => Types::int(),
                            'description' => 'Number of stories to be returned',
                            'defaultValue' => 10
                        ]
                    ]
                ],
                'lastStoryPosted' => [
                    'type' => Types::story(),
                    'description' => 'Returns last story posted for this blog'
                ],
                'deprecatedField' => [
                    'type' => Types::string(),
                    'deprecationReason' => 'This field is deprecated!'
                ],
                'fieldWithException' => [
                    'type' => Types::string(),
                    'resolve' => function() {
                        throw new \Exception("Exception message thrown in field resolver");
                    }
                ],
                'hello' => Type::string()
            ],
            'resolveField' => function($val, $args, $context, ResolveInfo $info) {
                return $this->{$info->fieldName}($val, $args, $context, $info);
            }
        ];
        parent::__construct($config);
    }

    public function user($rootValue, $args)
    {
        return DataSource::findUser($args['id']);
    }

    public function viewer($rootValue, $args, AppContext $context)
    {
        return $context->viewer;
    }

    public function stories($rootValue, $args)
    {
        $args += ['after' => null];
        return DataSource::findStories($args['limit'], $args['after']);
    }

    public function lastStoryPosted()
    {
        return DataSource::findLatestStory();
    }

    public function hello()
    {
        return 'Your graphql-php endpoint is ready! Use GraphiQL to browse API';
    }

    public function deprecatedField()
    {
        return 'You can request deprecated field, but it is not displayed in auto-generated documentation by default.';
    }
}
