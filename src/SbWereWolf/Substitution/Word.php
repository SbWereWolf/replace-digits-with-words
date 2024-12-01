<?php

namespace SbWereWolf\Substitution;

class Word
{
    readonly private int $length;
    readonly private string $symbols;
    readonly private int $number;

    public function __construct(
        private readonly string $original,
        private readonly string $digits,
    ) {
        $this->symbols = mb_strtolower($this->original);
        $this->length = mb_strlen($this->digits);
        $this->number = (int)$this->digits;
    }

    public function original(): string
    {
        return $this->original;
    }
    public function symbols(): string
    {
        return $this->symbols;
    }
    public function length(): string
    {
        return $this->length;
    }
    public function digits(): string
    {
        return $this->digits;
    }
    public function number(): string
    {
        return $this->number;
    }
}