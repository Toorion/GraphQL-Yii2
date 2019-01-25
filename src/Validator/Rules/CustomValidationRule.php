<?php

declare(strict_types=1);

namespace YiiGraphQL\Validator\Rules;

use YiiGraphQL\Error\Error;
use YiiGraphQL\Validator\ValidationContext;

class CustomValidationRule extends ValidationRule
{
    /** @var callable */
    private $visitorFn;

    public function __construct($name, callable $visitorFn)
    {
        $this->name      = $name;
        $this->visitorFn = $visitorFn;
    }

    /**
     * @return Error[]
     */
    public function getVisitor(ValidationContext $context)
    {
        $fn = $this->visitorFn;

        return $fn($context);
    }
}
