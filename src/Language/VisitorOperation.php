<?php

declare(strict_types=1);

namespace YiiGraphQL\Language;

class VisitorOperation
{
    /** @var bool */
    public $doBreak;

    /** @var bool */
    public $doContinue;

    /** @var bool */
    public $removeNode;
}
