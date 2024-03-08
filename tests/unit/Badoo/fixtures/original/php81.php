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

function something(...$arguments) {

}

#[TestAttribute(new stdClass())]
class testThings
{
    final public const CANNOT_OVERRIDE = 'the final word';

    public function __construct(
        public readonly One & Three $typeIntersection,
        protected readonly string $valueIsReadOnly = 'only read',
        private readonly stdClass $canInitializeNewClassHere = new stdClass(),
    ) {
    }

    public function testStoppingProgramFlow(): never
    {
        $value = 1 + 2;

        exit(666);
    }

    public function testMakeFunctionClosureWithSpreadOperator(One & Three $typeIntersection): void
    {
        $closureOne = array_merge(...);
        $closureTwo = something(...);
        $closureThree = $this->testStoppingProgramFlow(...);

        $closureOne(['will'], ['be'], ['merged'], ['some new octal notation:', 0o16, 0O33]);
        $closureTwo($typeIntersection, $typeIntersection);
    }
}

function testTypeIntersectionInFunction(One & Three $typeIntersection)
{
}

function neverReturns(): never
{
    exit(111);
}
