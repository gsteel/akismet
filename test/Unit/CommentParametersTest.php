<?php

declare(strict_types=1);

namespace GSteel\Akismet\Test;

use DateTimeImmutable;
use DateTimeZone;
use GSteel\Akismet\CommentParameters;
use GSteel\Akismet\CommentType;
use GSteel\Akismet\Exception\InvalidRequestParameters;
use Laminas\Diactoros\ServerRequestFactory;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

class CommentParametersTest extends TestCase
{
    /** @var CommentParameters */
    private $params;

    protected function setUp(): void
    {
        parent::setUp();
        $this->params = (new CommentParameters())
            ->withRequestParams('127.0.0.1')
            ->withComment('Whatever', CommentType::message())
            ->withHostInformation('https://www.example.com');
    }

    public function testThatItIsExceptionalToProvideAnUnknownParameter(): void
    {
        $this->expectException(InvalidRequestParameters::class);
        $this->expectExceptionMessage('The parameter "bing" is not a valid parameter name');
        new CommentParameters(['bing' => 'foo']);
    }

    public function testThatYouCannotRetrieveTheParameterListForAnInvalidPayload(): void
    {
        $this->expectException(InvalidRequestParameters::class);
        (new CommentParameters())->toParameterList();
    }

    public function testThatDefaultParametersYieldAnEmptyArray(): void
    {
        self::assertEquals(
            [],
            (new CommentParameters())->getArrayCopy()
        );
    }

    public function testThatHostUrlMustBeAValidUrl(): void
    {
        $this->expectException(InvalidRequestParameters::class);
        $this->params->withHostInformation('foo');
    }

    public function testTheTheUserIpMustBeAValidIpAddress(): void
    {
        $this->expectException(InvalidRequestParameters::class);
        $this->params->withRequestParams('foo');
    }

    public function testThatCommentValuesAreIncludedInTheParameterList(): void
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-01-01 12:34:56', new DateTimeZone('UTC'));

        $params = $this->params->withComment(
            'Whatever',
            CommentType::comment(),
            $date,
            'Some One',
            'me@example.com',
            'https://www.example.com'
        );

        $list = $params->toParameterList();
        self::assertEquals('Whatever', $list['comment_content']);
        self::assertEquals(CommentType::comment()->getValue(), $list['comment_type']);
        self::assertEquals('2020-01-01T12:34:56+00:00', $list['comment_date_gmt']);
        self::assertEquals('Some One', $list['comment_author']);
        self::assertEquals('me@example.com', $list['comment_author_email']);
        self::assertEquals('https://www.example.com', $list['comment_author_url']);
    }

    public function testThatTheCommentDateIsConvertedToUtc(): void
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2020-01-01 13:00:00', new DateTimeZone('NZST'));
        $expect = '2020-01-01T01:00:00+00:00';
        $params = $this->params->withComment(
            'Whatever',
            CommentType::comment(),
            $date
        );
        $list = $params->toParameterList();
        self::assertEquals($expect, $list['comment_date_gmt']);
    }

    public function testThatOptionalCommentParametersAreNotIncludedInTheParameterList(): void
    {
        $params = $this->params->withComment(
            'Whatever',
            CommentType::blogPost()
        );

        $list = $params->toParameterList();
        self::assertArrayNotHasKey('comment_author', $list);
        self::assertArrayNotHasKey('comment_author_email', $list);
        self::assertArrayNotHasKey('comment_author_url', $list);
    }

    public function testThatHostInformationIsIncludedInTheParameterList(): void
    {
        $params = $this->params->withHostInformation(
            'https://www.example.com',
            'en_gb',
            'utf-8'
        );

        $list = $params->toParameterList();
        self::assertEquals('https://www.example.com', $list['blog']);
        self::assertEquals('en_gb', $list['blog_lang']);
        self::assertEquals('utf-8', $list['blog_charset']);
    }

    public function testThatOptionalHostInformationIsNotIncludedInTheParameterList(): void
    {
        $params = $this->params->withHostInformation('https://www.example.com');
        $list = $params->toParameterList();
        self::assertArrayNotHasKey('blog_lang', $list);
        self::assertArrayNotHasKey('blog_charset', $list);
    }

    public function testThatTestModeCanBeEnabled(): void
    {
        $params = $this->params->markAsTest();
        $list = $params->toParameterList();
        self::assertArrayHasKey('is_test', $list);
    }

    public function testThatHoneyPotParametersAreReturnedInTheExpectedFormat(): void
    {
        $params = $this->params->withHoneyPot('my_field', 'some value');
        $list = $params->toParameterList();
        self::assertEquals('my_field', $list['honeypot_field_name']);
        self::assertEquals('some value', $list['my_field']);
        self::assertArrayNotHasKey('honeypot_field_value', $list);
    }

    public function testThatRequestParamsAreIncludedInTheParameterList(): void
    {
        $params = $this->params->withRequestParams(
            '127.0.0.1',
            'Some agent',
            'https://www.example.com',
            'https://other.example.com'
        );
        $list = $params->toParameterList();
        self::assertEquals('127.0.0.1', $list['user_ip']);
        self::assertEquals('Some agent', $list['user_agent']);
        self::assertEquals('https://www.example.com', $list['referrer']);
        self::assertEquals('https://other.example.com', $list['permalink']);
    }

    public function testThatOptionalRequestParamsAreFilteredFromOutput(): void
    {
        $params = $this->params->withRequestParams('127.0.0.1');
        $list = $params->toParameterList();
        self::assertArrayNotHasKey('user_agent', $list);
        self::assertArrayNotHasKey('referrer', $list);
        self::assertArrayNotHasKey('permalink', $list);
    }

    public function testThatMutatorsReturnNewInstances(): CommentParameters
    {
        $params = new CommentParameters();

        $withComment = $params->withComment(
            'Whatever',
            CommentType::contactForm(),
            null,
            'Some One',
            'me@example.com',
            'https://www.example.com'
        );

        self::assertNotSame($params, $withComment);

        $withHost = $withComment->withHostInformation('https://www.example.com');

        self::assertNotSame($withComment, $withHost);

        $withHoneyPot = $withHost->withHoneyPot('foo', 'bar');

        self::assertNotSame($withHost, $withHoneyPot);

        $withTest = $withHoneyPot->markAsTest();

        self::assertNotSame($withHoneyPot, $withTest);

        $withRequest = $withTest->withRequestParams('127.0.0.1');

        self::assertNotSame($withTest, $withRequest);

        return $withRequest;
    }

    /** @depends testThatMutatorsReturnNewInstances */
    public function testThatJsonEncodeRoundTripYieldsIdenticalData(CommentParameters $source): void
    {
        $jsonString = json_encode($source, JSON_THROW_ON_ERROR);
        $target = new CommentParameters(json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR));

        self::assertEquals(
            $source->toParameterList(),
            $target->toParameterList()
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function serverArrayDataProvider(): array
    {
        return [
            'Only IP' => [['REMOTE_ADDR' => '1.2.3.4']],
            'Missing Referrer' => [['REMOTE_ADDR' => '1.2.3.4', 'HTTP_USER_AGENT' => 'UA']],
            'All Three' => [['REMOTE_ADDR' => '1.2.3.4', 'HTTP_USER_AGENT' => 'UA', 'HTTP_REFERER' => 'https://www.example.com']],
        ];
    }

    /**
     * @param array<string, string> $serverParams
     *
     * @dataProvider serverArrayDataProvider
     */
    public function testThatAPsrRequestCanBeUsedToCreateAnInstance(array $serverParams): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://www.example.com', $serverParams);
        $expect = array_filter([
            'user_ip' => $serverParams['REMOTE_ADDR'],
            'user_agent' => $serverParams['HTTP_USER_AGENT'] ?? null,
            'referrer' => $serverParams['HTTP_REFERER'] ?? null,
            'permalink' => 'https://www.example.com',
        ]);

        self::assertEquals($expect, CommentParameters::fromRequest($request)->getArrayCopy());
    }

    /**
     * @param array<string, string> $serverParams
     *
     * @dataProvider serverArrayDataProvider
     */
    public function testThatAPsrRequestCanBeUsedToMutateAnInstance(array $serverParams): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://www.example.com', $serverParams);
        $expect = array_filter([
            'user_ip' => $serverParams['REMOTE_ADDR'],
            'user_agent' => $serverParams['HTTP_USER_AGENT'] ?? null,
            'referrer' => $serverParams['HTTP_REFERER'] ?? null,
            'permalink' => 'https://www.example.com',
        ]);

        self::assertEquals($expect, (new CommentParameters())->withRequest($request)->getArrayCopy());
    }
}
