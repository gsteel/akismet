<?php

declare(strict_types=1);

namespace GSteel\Akismet;

use JsonSerializable;

use function json_decode;

use const JSON_THROW_ON_ERROR;

final class Result implements JsonSerializable
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

    /** @return array{isSpam: bool, parameters: CommentParameters} */
    public function jsonSerialize(): array
    {
        return [
            'isSpam' => $this->isSpam,
            'parameters' => $this->parameters,
        ];
    }

    public static function fromJsonString(string $jsonString): self
    {
        $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);
        Assert::keyExists($data, 'isSpam');
        Assert::boolean($data['isSpam']);
        Assert::keyExists($data, 'parameters');
        Assert::isArray($data['parameters']);

        return new self(new CommentParameters($data['parameters']), $data['isSpam']);
    }
}
