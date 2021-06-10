<?php

declare(strict_types=1);

namespace GSteel\Akismet;

final class Result
{
    /** @var CommentParameters */
    private $parameters;
    /** @var bool */
    private $isSpam;

    public function __construct(CommentParameters $parameters, bool $isSpam)
    {
        $this->parameters = $parameters;
        $this->isSpam = $isSpam;
    }

    public function __invoke(): bool
    {
        return $this->isSpam;
    }

    public function isSpam(): bool
    {
        return $this->isSpam;
    }

    public function parameters(): CommentParameters
    {
        return $this->parameters;
    }
}
