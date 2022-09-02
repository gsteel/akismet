<?php

declare(strict_types=1);

namespace GSteel\Akismet;

use MyCLabs\Enum\Enum;

/**
 * @psalm-immutable
 * @extends Enum<non-empty-string>
 */
final class CommentType extends Enum
{
    private const COMMENT = 'comment';
    private const FORUM_POST = 'forum-post';
    private const REPLY = 'reply';
    private const BLOG_POST = 'blog-post';
    private const CONTACT_FORM = 'contact-form';
    private const SIGNUP = 'signup';
    private const MESSAGE = 'message';

    public static function comment(): self
    {
        return new self(self::COMMENT);
    }

    public static function forumPost(): self
    {
        return new self(self::FORUM_POST);
    }

    public static function reply(): self
    {
        return new self(self::REPLY);
    }

    public static function blogPost(): self
    {
        return new self(self::BLOG_POST);
    }

    public static function contactForm(): self
    {
        return new self(self::CONTACT_FORM);
    }

    public static function signup(): self
    {
        return new self(self::SIGNUP);
    }

    public static function message(): self
    {
        return new self(self::MESSAGE);
    }
}
