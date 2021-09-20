<?php
/**
 * Test case for Soft Mocks capabilities. Requires phpunit from badoo repo or with patches from composer.json
 * https://github.com/badoo/soft-mocks/blob/master/composer.json
 *
 * @author Yuriy Nasretdinov <y.nasretdinov@corp.badoo.com>
 */

class ExampleTest extends \PHPUnit\Framework\TestCase
{
    const EX_CLASS_CONST = 5;

    public function exampleFact($n)
    {
        if ($n <= 1) return 1;
        return $n * $this->exampleFact($n - 1);
    }

    public function exampleGenerator()
    {
        yield 1;
        yield 2;
    }

    public function tearDown()
    {
        \Badoo\SoftMocks::restoreAll();
    }

    public function testFunction()
    {
        \Badoo\SoftMocks::redefineFunction('md5', '$a', 'return "hash";');
        $this->assertEquals('hash', md5("a"));
    }

    public function testConstant()
    {
        define('SOME_CONST', 3);
        \Badoo\SoftMocks::redefineConstant('SOME_CONST', 4);
        $this->assertEquals(4, SOME_CONST);
    }

    public function testClassConstant()
    {
        \Badoo\SoftMocks::redefineConstant(self::class . '::EX_CLASS_CONST', 6);
        $this->assertEquals(6, self::EX_CLASS_CONST);
    }

    public function testMethod()
    {
        \Badoo\SoftMocks::redefineMethod(self::class, 'exampleFact', '$n', 'return -1;');
        $this->assertEquals(-1, $this->exampleFact(4));
        $this->assertEquals(-4, \Badoo\SoftMocks::callOriginal([$this, 'exampleFact'], [4]));
    }

    public function testGenerator()
    {
        \Badoo\SoftMocks::redefineGenerator(
            self::class,
            'exampleGenerator',
            [$this, 'getGeneratorMock']
        );

        $all_values = [];
        foreach ($this->exampleGenerator() as $v) {
            $all_values[] = $v;
        }

        $this->assertEquals([3, 4, 5], $all_values);
    }

    public function getGeneratorMock()
    {
        yield 3;
        yield 4;
        yield 5;
    }
}
