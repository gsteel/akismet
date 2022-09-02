<?php

declare(strict_types=1);

namespace GSteel\Akismet\Test;

use GSteel\Akismet\CommentParameters;
use GSteel\Akismet\CommentType;
use GSteel\Akismet\Result;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

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

    public function testThatResultsSurviveAJsonEncodeRoundTrip(): void
    {
        $params = $this->params->withComment('Foo', CommentType::signup());
        $original = new Result($params, true);
        $jsonString = json_encode($original, JSON_THROW_ON_ERROR);
        $hydrated = Result::fromJsonString($jsonString);

        self::assertEquals(
            $original->parameters()->getArrayCopy(),
            $hydrated->parameters()->getArrayCopy(),
        );

        self::assertTrue($hydrated->isSpam());
    }
}
