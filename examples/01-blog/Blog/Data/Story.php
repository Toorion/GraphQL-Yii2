<?php
namespace YiiGraphQL\Examples\Blog\Data;

use YiiGraphQL\Utils\Utils;

class Story
{
    public $id;

    public $authorId;

    public $title;

    public $body;

    public $isAnonymous = false;

    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
