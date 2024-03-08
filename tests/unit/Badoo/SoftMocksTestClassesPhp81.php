<?php

namespace Badoo\SoftMock\Tests;

function functionToUseWithSpreadOperator(int $numberOne, int $numberTwo): int
{
    return $numberOne + $numberTwo;
}

function functionToTestClosureWithSpreadOperator(): int
{
    $closure = functionToUseWithSpreadOperator(...);

    return $closure(1, 2);
}

enum myEnum: string
{
    case one = '1';
    case two = '2';
    case three = '3';
    public const TEST = 'me';

    public static function getDefault(): self
    {
        return self::three;
    }

    public static function getTest(): string
    {
        return self::TEST;
    }

    public function isLarge(int $addThis): bool
    {
        return $this->toInt($addThis) >= 5;
    }

    private function toInt(int $addThis): int
    {
        return (int)$this->value + $addThis;
    }
}
