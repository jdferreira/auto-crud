<?php

namespace Ferreira\AutoCrud\Stub;

class StubPart
{
    private $type;
    private $payload;
    private $indentation = '';
    private $removeBefore = 0;
    private $removeAfter = 0;

    private function __construct(string $type)
    {
        $this->type = $type;
    }

    public static function literal(string $literal): self
    {
        $part = new self('literal');
        $part->payload = $literal;

        return $part;
    }

    public static function placeholder(string $placeholder): self
    {
        $part = new self('placeholder');
        $part->payload = $placeholder;

        return $part;
    }

    public function isLiteral(): bool
    {
        return $this->type === 'literal';
    }

    public function isPlaceholder(): bool
    {
        return $this->type === 'placeholder';
    }

    public function setIndentation(string $indentation): self
    {
        $this->indentation = $indentation;

        return $this;
    }

    public function setAmountToRemove(int $before, int $after): self
    {
        $this->removeBefore = $before;
        $this->removeAfter = $after;

        return $this;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getIndentation(): string
    {
        return $this->indentation;
    }

    public function getAmountToRemoveBefore(): int
    {
        return $this->removeBefore;
    }

    public function getAmountToRemoveAfter(): int
    {
        return $this->removeAfter;
    }
}
