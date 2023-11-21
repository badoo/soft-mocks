<?php

class NewTypes {
    public int|float $intOrFloatPublicProperty;
    protected int|float $intOrFloatProtectedProperty;
    private int|float $intOrFloatPrivateProperty;

    public function intOrFloatArgument(int|float $intOrFloat): void {
        return;
    }

    public function intOrFloatResult(): int|float {
        return 1.0;
    }

    public function intOrFloatArgumentAndResult(int|float $intOrFloat): int|float {
        return $intOrFloat;
    }

    public function mixedArgumentAndResult(mixed $mixed): mixed {
        return $mixed ?? $this->intOrFloatResult();
    }
}

function intOrFloatArgumentAndResult(int|float $intOrFloat): int|float {
    return $intOrFloat;
}

function nullsafeUsagee() {
    function returnNull() {
        return null;
    }

    return returnNull()?->test();
}

function multipleArguments($arg1, ?int $arg2 = null, int $arg3 = 1): void
{}

multipleArguments(arg1: 'arg1', arg3: 10);

#[Attribute]
class TestAttribute
{
    public const TEST_VALUE = 'test-value';
    private string $event;

    public function __construct(string $event)
    {
        $this->event = $event;
    }
}

#[TestAttribute('event')]
class TestAttributeUser1
{
    public function foo(): void {}
}

#[
    TestAttribute('event'),
    TestAttribute('event2')
]
class TestAttributeUser2
{
    public function foo(): void {}

    #[TestAttribute('event3')]
    protected function bar(#[TestAttribute('event4')] $bar): void {}

    #[TestAttribute(TestAttribute::TEST_VALUE)]
    protected function bar(#[TestAttribute(TestAttribute::TEST_VALUE)] $bar): void {}
}

function matchTest(int $input): string
{
    return match($input) {
        415 => 'teapot!',
        default => 'mkay',
    };
}

class ConstructorPropertyPromotion 
{
    public function __construct(
        public int $publicInt,
        protected int $protectedInt,
        private int $privateInt,
    ) {
    }
}

class StaticReturn
{
    public static function instance(): static {
        return new static();
    }

    public static function getClassName(): string {
        $obj = static::instance();
        return $obj::class;
    }
}

function throwExpression(mixed $input) {
    return $input ?? throw new \Exception();
}

try {
    $foo = 'bar';
} catch (\Exception) {
    $foo = 'baz';
}