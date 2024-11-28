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

    public function testStoppingProgramFlowWithExit(): never
    {
        $value = 1 + 2;

        exit(666);
    }

    /**
     * @throws Exception
     */
    public function testStoppingProgramFlowWithException(): never
    {
        $value = 1 + 2;

        throw new \Exception('Some exception');
    }

    public function testMakeFunctionClosureWithSpreadOperator(One & Three $typeIntersection): void
    {
        $closureOne = array_merge(...);
        $closureTwo = something(...);
        $closureThree = $this->testStoppingProgramFlowWithExit(...);

        $closureOne(['will'], ['be'], ['merged'], ['some new octal notation:', 0o16, 0O33]);
        $closureTwo($typeIntersection, $typeIntersection);
    }
}

function testTypeIntersectionInFunction(One & Three $typeIntersection)
{
}

function neverReturnsWithExit(): never
{
    exit(111);
}

function neverReturnsWithException(): never
{
    throw new \RuntimeException('Some runtime exception');
}
