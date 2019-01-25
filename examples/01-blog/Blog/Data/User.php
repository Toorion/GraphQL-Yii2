<?php
namespace YiiGraphQL\Examples\Blog\Data;

use YiiGraphQL\Utils\Utils;

class User
{
    public $id;

    public $email;

    public $firstName;

    public $lastName;

    public $hasPhoto;

    public function __construct(array $data)
    {
        Utils::assign($this, $data);
    }
}
