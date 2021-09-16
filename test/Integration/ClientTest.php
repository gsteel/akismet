<?php

declare(strict_types=1);

namespace GSteel\Akismet\IntegrationTest;

use GSteel\Akismet\Client;
use GSteel\Akismet\CommentParameters;
use GSteel\Akismet\CommentType;
use Laminas\Diactoros\RequestFactory;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;

use function getenv;

final class ClientTest extends TestCase
{
    /** @var string */
    private $apiKey;
    /** @var string */
    private $blog;
    /** @var Client */
    private $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKey = getenv('AKISMET_KEY');
        if ($this->apiKey === false) {
            self::markTestSkipped('There is no API key in the environment variable AKISMET_KEY');
        }

        $this->blog = getenv('AKISMET_BLOG');
        if ($this->blog === false) {
            self::markTestSkipped('There is no website url in the environment variable AKISMET_BLOG');
        }

        $this->client = $this->clientFactory($this->apiKey, $this->blog);
    }

    private function clientFactory(string $key, string $url): Client
    {
        return new Client(
            $key,
            $url,
            new \Http\Client\Curl\Client(
                new ResponseFactory(),
                new StreamFactory()
            ),
            new RequestFactory(),
            new StreamFactory()
        );
    }

    public function testValidApiKey(): void
    {
        self::assertTrue($this->client->verifyKey($this->apiKey, $this->blog));
    }

    /** @depends testValidApiKey */
    public function testSpam(): void
    {
        $params = (new CommentParameters())
            ->markAsTest()
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::comment(), null, 'viagra-test-123');

        self::assertTrue($this->client->check($params)->isSpam());
    }

    /** @depends testValidApiKey */
    public function testHam(): void
    {
        $params = (new CommentParameters(['user_role' => 'administrator']))
            ->markAsTest()
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::comment());

        self::assertFalse($this->client->check($params)->isSpam());
    }

    /** @depends testValidApiKey */
    public function testSubmitHamOperatesWithoutObviousError(): void
    {
        $params = (new CommentParameters())
            ->markAsTest()
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::comment());

        $this->client->submitHam($params);
        $this->addToAssertionCount(1);
    }

    /** @depends testValidApiKey */
    public function testSubmitSpamOperatesWithoutObviousError(): void
    {
        $params = (new CommentParameters())
            ->markAsTest()
            ->withRequestParams('127.0.0.1')
            ->withComment('Anything', CommentType::comment());

        $this->client->submitSpam($params);
        $this->addToAssertionCount(1);
    }
}
