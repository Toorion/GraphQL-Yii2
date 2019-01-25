<?php
namespace YiiGraphQL\Examples\Blog\Type;

use YiiGraphQL\Examples\Blog\Data\Story;
use YiiGraphQL\Examples\Blog\Data\User;
use YiiGraphQL\Examples\Blog\Data\Image;
use YiiGraphQL\Examples\Blog\Types;
use YiiGraphQL\Type\Definition\InterfaceType;

class NodeType extends InterfaceType
{
    public function __construct()
    {
        $config = [
            'name' => 'Node',
            'fields' => [
                'id' => Types::id()
            ],
            'resolveType' => [$this, 'resolveNodeType']
        ];
        parent::__construct($config);
    }

    public function resolveNodeType($object)
    {
        if ($object instanceof User) {
            return Types::user();
        } else if ($object instanceof Image) {
            return Types::image();
        } else if ($object instanceof Story) {
            return Types::story();
        }
    }
}
