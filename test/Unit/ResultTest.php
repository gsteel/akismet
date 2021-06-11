<?php

declare(strict_types=1);

namespace GSteel\Akismet\Test;

use GSteel\Akismet\CommentParameters;
use GSteel\Akismet\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    /** @var CommentParameters */
    private $params;

    protected function setUp(): void
    {
        parent::setUp();
        $this->params = new CommentParameters();
    }

    public function testSpamIsSpam(): void
    {
        $result = new Result($this->params, true);
        self::assertTrue($result->isSpam());
    }

    public function testHamIsHam(): void
    {
        $result = new Result($this->params, false);
        self::assertFalse($result->isSpam());
    }

    public function testParametersAreTheSameInstance(): void
    {
        $result = new Result($this->params, false);
        self::assertSame($this->params, $result->parameters());
    }

    public function testInvokeYieldsSpamResult(): void
    {
        $result = new Result($this->params, true);
        self::assertTrue($result());
        $result = new Result($this->params, false);
        self::assertFalse($result());
    }
}
