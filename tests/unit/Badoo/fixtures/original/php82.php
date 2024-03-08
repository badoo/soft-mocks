<?php

interface One
{
}

interface Two
{
}

interface Three
{
}

trait hasConstant
{
    public const TRAIT_CONSTANT = 'from trait';
}

readonly class doNotWriteMe
{
    use hasConstant;

    public function __construct(private string $readOnlyValue = self::TRAIT_CONSTANT)
    {
    }
}

class newTypeRules
{
    public true $true = true;
    protected false $false = false;
    private null $null = null;

    public function beConfused((One & Three)|Two $value): (One & Three)|Two
    {
        return $value;
    }

    public function getValue(bool $seed): bool
    {
        return $this->getTruth($seed) || $this->getLie(!$seed);
    }

    protected function getTruth(true|null $seed): true|null
    {
        return $seed;
    }

    private function getLie(false|null $seed): false|null
    {
        return $seed;
    }
}

enum A: string
{
    case B = 'B';

    const C = [self::B?->value => self::B];
}
