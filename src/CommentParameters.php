<?php

declare(strict_types=1);

namespace GSteel\Akismet;

use DateTimeInterface;
use DateTimeZone;
use GSteel\Akismet\Exception\InvalidRequestParameters;
use JsonSerializable;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_merge;
use function assert;
use function in_array;
use function is_string;
use function sprintf;

final class CommentParameters implements JsonSerializable
{
    /** @var string[] */
    private static $validKeys = [
        'blog',
        'user_ip',
        'user_agent',
        'referrer',
        'permalink',
        'comment_type',
        'comment_author',
        'comment_author_email',
        'comment_author_url',
        'comment_content',
        'comment_date_gmt',
        'comment_post_modified_gmt',
        'blog_lang',
        'blog_charset',
        'user_role',
        'is_test',
        'recheck_reason',
        'honeypot_field_name',
        'honeypot_field_value',
    ];
    /** @var array<string, mixed> */
    private $storage = [];

    /** @param  array<string, mixed> $parameters */
    public function __construct(array $parameters = [])
    {
        foreach ($parameters as $parameter => $value) {
            $this->set($parameter, $value);
        }
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $serverParams = $request->getServerParams();

        return (new self())->withRequestParams(
            $serverParams['REMOTE_ADDR'] ?? null,
            $serverParams['HTTP_USER_AGENT'] ?? null,
            $serverParams['HTTP_REFERER'] ?? null,
            (string) $request->getUri()
        );
    }

    public function withRequest(ServerRequestInterface $request): self
    {
        return $this->merge(self::fromRequest($request));
    }

    /**
     * @throws InvalidRequestParameters If the IP address is invalid, or any URL parameters don't validate.
     */
    public function withRequestParams(
        string $ipAddress,
        ?string $userAgent = null,
        ?string $referrer = null,
        ?string $permalink = null
    ): self {
        Assert::ip($ipAddress);
        Assert::nullOrUrl($referrer);
        Assert::nullOrUrl($permalink);

        return $this->merge(new self(array_filter([
            'user_ip'    => $ipAddress,
            'user_agent' => $userAgent,
            'referrer'   => $referrer,
            'permalink'  => $permalink,
        ])));
    }

    /**
     * @throws InvalidRequestParameters If any email or url parameters and not valid.
     */
    public function withComment(
        string $comment,
        CommentType $type,
        ?DateTimeInterface $commentDate = null,
        ?string $authorName = null,
        ?string $authorEmail = null,
        ?string $authorUrl = null
    ): self {
        Assert::nullOrEmail($authorEmail);
        Assert::nullOrUrl($authorUrl);

        $commentDate = $commentDate ? $commentDate->setTimezone(new DateTimeZone('UTC')) : null;

        $data = array_filter([
            'comment_content' => $comment,
            'comment_type' => $type->getValue(),
            'comment_date_gmt' => $commentDate ? $commentDate->format(DateTimeInterface::ATOM) : null,
            'comment_author' => $authorName,
            'comment_author_email' => $authorEmail,
            'comment_author_url' => $authorUrl,
        ]);

        return $this->merge(new self($data));
    }

    public function markAsTest(): self
    {
        return $this->merge(new self(['is_test' => 1]));
    }

    public function withHoneyPot(string $fieldName, string $fieldValue): self
    {
        return $this->merge(new self([
            'honeypot_field_name' => $fieldName,
            'honeypot_field_value' => $fieldValue,
        ]));
    }

    /**
     * @throws InvalidRequestParameters if the website url is not a complete and valid url.
     */
    public function withHostInformation(
        string $websiteUrl,
        ?string $websiteLanguage = null,
        ?string $websiteCharset = null
    ): self {
        Assert::url($websiteUrl);

        return $this->merge(new self(array_filter([
            'blog' => $websiteUrl,
            'blog_lang' => $websiteLanguage,
            'blog_charset' => $websiteCharset,
        ])));
    }

    private function assertValid(): void
    {
        $message = null;
        if ($this->isValid($message)) {
            return;
        }

        assert(is_string($message));

        throw InvalidRequestParameters::withMessage($message);
    }

    public function isValid(?string &$message = null): bool
    {
        try {
            Assert::keyExists(
                $this->storage,
                'user_ip',
                'The IP Address of the remote user is required parameter'
            );
            Assert::keyExists(
                $this->storage,
                'blog',
                'The website address of the target website is a required parameter'
            );
            Assert::keyExists(
                $this->storage,
                'comment_content',
                'The comment content is a required parameter'
            );
            Assert::keyExists(
                $this->storage,
                'comment_type',
                'The comment type is a required parameter'
            );

            return true;
        } catch (InvalidRequestParameters $error) {
            $message = $error->getMessage();

            return false;
        }
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->storage;
    }

    /** @return array<string, mixed> */
    public function getArrayCopy(): array
    {
        return $this->storage;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidRequestParameters if the payload is missing any required parameters.
     */
    public function toParameterList(): array
    {
        $this->assertValid();
        $list = $this->storage;
        if (isset($list['honeypot_field_name'])) {
            $list[$list['honeypot_field_name']] = $list['honeypot_field_value'];
            unset($list['honeypot_field_value']);
        }

        return $list;
    }

    private function merge(self $other): self
    {
        $data = array_merge($this->storage, $other->storage);

        return new self($data);
    }

    /**
     * @throws InvalidRequestParameters if the parameter name is not recognised.
     */
    private function assertValidOffset(string $offset): void
    {
        if (! in_array($offset, self::$validKeys, true)) {
            throw new InvalidRequestParameters(sprintf(
                'The parameter "%s" is not a valid parameter name',
                $offset
            ));
        }
    }

    /**
     * @param mixed $value
     */
    private function set(string $offset, $value): void
    {
        $this->assertValidOffset($offset);
        $this->storage[$offset] = $value;
    }
}
