<?php

declare(strict_types=1);

namespace GSteel\Akismet;

use DateTimeImmutable;
use DateTimeInterface;
use GSteel\Akismet\Exception\InvalidRequestParameters;
use JsonSerializable;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_merge;
use function assert;
use function in_array;
use function is_string;
use function sprintf;

/**
 * @psalm-type ParameterArray = array{
 *     blog?: string|null,
 *     user_ip?: string|null,
 *     user_agent?: string|null,
 *     referrer?: string|null,
 *     permalink?: string|null,
 *     comment_type?: string|null,
 *     comment_author?: string|null,
 *     comment_author_email?: string|null,
 *     comment_author_url?: string|null,
 *     comment_content?: string|null,
 *     comment_date_gmt?: string|null,
 *     comment_post_modified_gmt?: string|null,
 *     blog_lang?: string|null,
 *     blog_charset?: string|null,
 *     user_role?: string|null,
 *     is_test?: int|null,
 *     recheck_reason?: string|null,
 *     honeypot_field_name?: string|null,
 *     honeypot_field_value?: string|null,
 * }&array<string, string|int|null>
 */
final class CommentParameters implements JsonSerializable
{
    private const VALID_KEYS = [
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

    /** @psalm-var ParameterArray */
    private array $storage = [];

    /** @param ParameterArray $parameters */
    public function __construct(array $parameters = [])
    {
        foreach ($parameters as $parameter => $value) {
            $this->set($parameter, $value);
        }
    }

    public static function fromRequest(ServerRequestInterface $request): self
    {
        $serverParams = $request->getServerParams();
        $values = [];
        $keys = ['REMOTE_ADDR', 'HTTP_USER_AGENT', 'HTTP_REFERER'];
        foreach ($keys as $key) {
            $value = isset($serverParams[$key]) && is_string($serverParams[$key]) ? $serverParams[$key] : null;
            $values[$key] = $value;
        }

        /**
         * @psalm-var array{
         *     REMOTE_ADDR: string|null,
         *     HTTP_USER_AGENT: string|null,
         *     HTTP_REFERER: string|null,
         * } $values
         */

        Assert::notNull($values['REMOTE_ADDR']);

        return (new self())->withRequestParams(
            $values['REMOTE_ADDR'],
            $values['HTTP_USER_AGENT'],
            $values['HTTP_REFERER'],
            (string) $request->getUri(),
        );
    }

    public function withRequest(ServerRequestInterface $request): self
    {
        return $this->merge(self::fromRequest($request));
    }

    /** @throws InvalidRequestParameters If the IP address is invalid, or any URL parameters don't validate. */
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

    /** @throws InvalidRequestParameters If any email or url parameters and not valid. */
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

        $date = $commentDate
            ? DateTimeImmutable::createFromFormat('U', (string) $commentDate->getTimestamp())
            : null;

        $data = array_filter([
            'comment_content' => $comment,
            'comment_type' => $type->getValue(),
            'comment_date_gmt' => $date ? $date->format(DateTimeInterface::ATOM) : null,
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

    /** @throws InvalidRequestParameters if the website url is not a complete and valid url. */
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

    public function websiteUrl(): ?string
    {
        return $this->storage['blog'] ?? null;
    }

    public function withWebsiteUrl(string $url): self
    {
        Assert::url($url);

        return $this->merge(new self(['blog' => $url]));
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
                'The IP Address of the remote user is required parameter',
            );
            Assert::keyExists(
                $this->storage,
                'blog',
                'The website address of the target website is a required parameter',
            );
            Assert::keyExists(
                $this->storage,
                'comment_content',
                'The comment content is a required parameter',
            );
            Assert::keyExists(
                $this->storage,
                'comment_type',
                'The comment type is a required parameter',
            );

            return true;
        } catch (InvalidRequestParameters $error) {
            $message = $error->getMessage();

            return false;
        }
    }

    /** @return ParameterArray */
    public function jsonSerialize(): array
    {
        return $this->storage;
    }

    /** @return ParameterArray */
    public function getArrayCopy(): array
    {
        return $this->storage;
    }

    /**
     * @return array<string, int|string|null>
     *
     * @throws InvalidRequestParameters if the payload is missing any required parameters.
     */
    public function toParameterList(): array
    {
        $this->assertValid();
        $list = $this->storage;
        if (isset($list['honeypot_field_name']) && isset($list['honeypot_field_value'])) {
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

    /** @throws InvalidRequestParameters if the parameter name is not recognised. */
    private function assertValidOffset(string $offset): void
    {
        if (! in_array($offset, self::VALID_KEYS, true)) {
            throw new InvalidRequestParameters(sprintf(
                'The parameter "%s" is not a valid parameter name',
                $offset,
            ));
        }
    }

    /** @param string|int|null $value */
    private function set(string $offset, $value): void
    {
        $this->assertValidOffset($offset);
        $this->storage[$offset] = $value;
    }
}
