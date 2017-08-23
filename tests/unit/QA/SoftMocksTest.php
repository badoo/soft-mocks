<?php
/**
 * Mocks core that rewrites code
 * @author Kirill Abrosimov <k.abrosimov@corp.badoo.com>
 * @author Rinat Akhmadeev <r.akhmadeev@corp.badoo.com>
 */
namespace QA;

class SoftMocksTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp()
    {
        require_once __DIR__ . '/SoftMocksTestClasses.php';
    }

    protected function tearDown()
    {
        SoftMocks::restoreAll();
        parent::tearDown();
    }

    public function testParserVersion()
    {
        $composer_json = file_get_contents(__DIR__ . '/../../../composer.json');
        static::assertNotEmpty($composer_json, "Can't get content from composer.json file");
        $composer_data = json_decode($composer_json, true);
        static::assertSame(
            JSON_ERROR_NONE,
            json_last_error(),
            "Can't parse composer.json: [" . json_last_error() . '] ' . json_last_error_msg()
        );
        static::assertSame(\QA\SoftMocks::PARSER_VERSION, $composer_data['require']['nikic/php-parser']);
    }

    public function testExitMock()
    {
        SoftMocks::redefineExit(
            '',
            function ($code) {
                throw new \Exception("exit called: {$code}");
            }
        );
        try {
            $result = WithExitTestClass::doWithExit();
            $this->fail('exception expected');
        } catch (\Exception $Exception) {
            static::assertEquals('exit called: ' . (WithExitTestClass::RESULT * 10), $Exception->getMessage());
            $result = false;
        }
        static::assertFalse($result);

        SoftMocks::restoreExit();

        SoftMocks::redefineExit(
            '',
            function ($code) {
                \QA\WithExitTestClass::$exit_code = $code;
                \QA\WithExitTestClass::$exit_called = true;
            }
        );
        $result = WithExitTestClass::doWithExit();
        static::assertEquals(WithExitTestClass::RESULT, $result);
        static::assertEquals(WithExitTestClass::RESULT * 10, WithExitTestClass::$exit_code);
        static::assertTrue(WithExitTestClass::$exit_called);
        SoftMocks::restoreExit();
    }

    public function testRedefineConstructor()
    {
        SoftMocks::redefineMethod(
            ConstructTestClass::class,
            '__construct',
            '',
            '$this->constructor_params = $mm_func_args;'
        );
        $object = new ConstructTestClass(1, 2, 3);
        static::assertEquals(42, $object->doSomething());
        static::assertEquals([1, 2, 3], $object->getConstructorParams());
    }

    public function testRedefineMethod()
    {
        SoftMocks::redefineMethod(
            BaseInheritanceTestClass::class,
            'doSomething',
            '',
            'return 43;'
        );
        $a = new BaseInheritanceTestClass();
        static::assertEquals(43, $a->doSomething());
    }

    public function testRedefineMethodWithInheritedClasses()
    {
        SoftMocks::redefineMethod(
            BaseInheritanceTestClass::class,
            'doSomething',
            '',
            'return 43;'
        );
        $a = new InheritanceTestClass();
        static::assertEquals(43, $a->doSomething());
    }

    public function testRedefineMethodWithInheritedClasses2()
    {
        SoftMocks::redefineMethod(
            InheritanceTestClass::class,
            'doSomething',
            '',
            'return 43;'
        );
        $a = new InheritanceTestClass();
        static::assertEquals(43, $a->doSomething());
    }

    public function testParentMismatch()
    {
        SoftMocks::redefineMethod(
            ParentMismatchChildTestClass::class,
            'f',
            '$c',
            'return \Qa\SoftMocks::callOriginal([\Qa\ParentMismatchChildTestClass::class, "f"], [$c]) + 1;'
        );
        SoftMocks::redefineMethod(ParentMismatchBaseTestClass::class, 'f', '', 'return 100;');

        static::assertEquals(2, ParentMismatchChildTestClass::f(true));
        static::assertEquals(101, ParentMismatchChildTestClass::f(false));
    }

    public function testGenerators()
    {
        $Generators = new GeneratorsTestClass();
        $values = [];
        SoftMocks::redefineGenerator(
            GeneratorsTestClass::class,
            'yieldAb',
            [$this, 'yieldAbMock']
        );

        foreach ($Generators->yieldAb(1) as $value) {
            $values[] = $value;
        }

        static::assertEquals(
            array_values(range(0, 9)),
            $values
        );
    }

    public function yieldAbMock()
    {
        for ($i = 0; $i <= 9; $i++) {
            yield $i;
        }
    }

    public function testGeneratorsRef()
    {
        $Generators = new GeneratorsTestClass();
        $values = [];

        SoftMocks::redefineGenerator(
            GeneratorsTestClass::class,
            'yieldRef',
            [$this, 'yieldRefMock']
        );

        foreach ($Generators->yieldRef(10) as $value) {
            $value++;
            $values[] = $value;
        }
        unset($value);

        static::assertEquals(
            array_values(range(1, 10)),
            $values
        );

        $values = [];

        foreach ($Generators->yieldRef(10) as &$value) {
            $value++;
            $values[] = $value;
        }
        unset($value);

        static::assertEquals(
            array_values(range(1, 9, 2)),
            $values
        );
    }

    public function &yieldRefMock($num)
    {
        for ($i = 0; $i < $num; $i++) {
            yield $i;
        }
    }

    public function testDefault()
    {
        $Misc = new DefaultTestClass();
        static::assertTrue($Misc->doSomething(1, 2));

        SoftMocks::redefineMethod(
            DefaultTestClass::class,
            'doSomething',
            '$a, $b = 3',
            'return [$a, $b];'
        );

        static::assertEquals([1, 2], $Misc->doSomething(1, 2));
        static::assertEquals([1, 3], $Misc->doSomething(1));
    }

    public function testInheritMock()
    {
        SoftMocks::redefineMethod(
            EmptyTestClass::class,
            'getter',
            '',
            'return 20;'
        );
        $Child = new EmptyEmptyTestClass();
        static::assertSame(20, $Child->getter());
        $Test = new EmptyTestClass();
        static::assertSame(20, $Test->getter());
        $Parent = new BaseTestClass();
        static::assertSame(10, $Parent->getter());
    }

    public function testInheritTrapMock()
    {
        SoftMocks::redefineMethod(
            BaseTestClass::class,
            'getter',
            '',
            'return 30;'
        );
        $Child = new ReplacingParentTestClass();
        static::assertEquals(20, $Child->getter());

        $Parent = new BaseTestClass();
        static::assertEquals(30, $Parent->getter());
    }

    public function testInheritParentMock()
    {
        SoftMocks::redefineMethod(
            BaseTestClass::class,
            'getter',
            '',
            'return 7;'
        );
        $Child = new EmptyParentTestClass();
        $res = $Child->getter();
        static::assertSame(14, $res);
    }

    public function testInheritStaticMock()
    {
        SoftMocks::redefineMethod(
            get_parent_class(GrandChildStaticTestClass::class),
            'getString',
            '',
            'return "D";'
        );
        static::assertSame('CD', GrandChildStaticTestClass::getString());
    }

    public function testAnonymous()
    {
        if (version_compare(phpversion(), '7.0.0', '<')) {
            static::markTestSkipped('PHP do not support anonymous classes');
        }

        require_once __DIR__ . '/AnonymousTestClass.php';
        static::assertEquals(1, AnonymousTestClass::SOMETHING);
        $test = new AnonymousTestClass();
        $obj = $test->doSomething();
        SoftMocks::redefineMethod(
            get_class($obj),
            'meth',
            '',
            'return "Test";'
        );
        static::assertSame('Test', $obj->meth());
    }
}
