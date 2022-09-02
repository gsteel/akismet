<?php

declare(strict_types=1);

namespace GSteel\Akismet;

use GSteel\Akismet\Exception\ApiError;
use GSteel\Akismet\Exception\HttpError;

interface AkismetClient
{
    /** @internal */
    public const VERIFY_KEY_URI = 'https://rest.akismet.com/1.1/verify-key';

    /** @internal */
    public const API_URI_TEMPLATE = 'https://%1$s.rest.akismet.com/1.1/%2$s';

    /** @internal */
    public const CHECK_ACTION = 'comment-check';

    /** @internal */
    public const SUBMIT_SPAM_ACTION = 'submit-spam';

    /** @internal */
    public const SUBMIT_HAM_ACTION = 'submit-ham';

    /** @internal */
    public const USER_AGENT = 'gsteel/akismet PHP API Client/1.3.0';

    /**
     * Verify the configured, or the given Akismet key is usable with the given website address
     *
     * @link https://akismet.com/development/api/#verify-key
     *
     * @param string|null $apiKey Optional Api Key that defaults to the key configured in the instance
     *
     * @throws HttpError If communicating with the API is not possible.
     */
    public function verifyKey(?string $apiKey = null, ?string $websiteUri = null): bool;

    /**
     * Classify a comment/request as spam or not
     *
     * @link https://akismet.com/development/api/#comment-check
     *
     * @throws HttpError If communicating with the API is not possible.
     * @throws ApiError If the API cannot process the request, for example and authentication failure.
     */
    public function check(CommentParameters $parameters): Result;

    /**
     * Submit a comment/request as Un-caught SPAM
     *
     * @link https://akismet.com/development/api/#submit-spam
     *
     * @throws HttpError If communicating with the API is not possible.
     * @throws ApiError If the API cannot process the request, for example and authentication failure.
     */
    public function submitSpam(CommentParameters $parameters): void;

    /**
     * Submit a comment/request as "Ham"
     *
     * @link https://akismet.com/development/api/#submit-ham
     *
     * @throws HttpError If communicating with the API is not possible.
     * @throws ApiError If the API cannot process the request, for example and authentication failure.
     */
    public function submitHam(CommentParameters $parameters): void;
}
