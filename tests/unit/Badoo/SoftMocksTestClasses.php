<?php
/**
 * @author Kirill Abrosimov <k.abrosimov@corp.badoo.com>
 * @author Rinat Akhmadeev <r.akhmadeev@corp.badoo.com>
 */
namespace Badoo\SoftMock\Tests;

class DefaultTestClass
{
    const VALUE = 1;

    public static function getValue()
    {
        return self::VALUE;
    }

    public function doSomething($a, $b = [])
    {
        return true;
    }
}

class ConstructTestClass
{
    private $constructor_params;

    public function __construct()
    {
        throw new \RuntimeException("Constructor not intercepted!");
    }

    public function doSomething()
    {
        return 42;
    }

    public function getConstructorParams()
    {
        return $this->constructor_params;
    }
}

class BaseInheritanceTestClass
{
    public function doSomething()
    {
        return 42;
    }
}

class InheritanceTestClass extends BaseInheritanceTestClass
{
    public function otherFunction()
    {
        return 88;
    }
}

class ParentMismatchBaseTestClass
{
    public static function f($c)
    {
        return 10;
    }
}

class ParentMismatchChildTestClass extends ParentMismatchBaseTestClass
{
    public static function f($c)
    {
        if ($c === true) {
            return 1;
        }

        return parent::f($c);
    }
}

class GeneratorsTestClass
{
    public function yieldAb($num)
    {
        yield "a";
        yield "b";
    }

    public function &yieldRef($num)
    {
        $a = "a";
        yield $a;
    }
}

class BaseTestClass
{
    public function getter()
    {
        return 10;
    }
}

class EmptyTestClass extends BaseTestClass {}

class EmptyEmptyTestClass extends EmptyTestClass {}

class ParentTestClass extends BaseTestClass
{
    public function getter()
    {
        return parent::getter() * 2;
    }
}

class ReplacingParentTestClass extends BaseTestClass
{
    public function getter()
    {
        return 20;
    }
}

class EmptyParentTestClass extends ParentTestClass {}

class BaseStaticTestClass
{
    public static function getString()
    {
        return 'A';
    }
}

class ChildStaticTestClass extends BaseStaticTestClass {}

class GrandChildStaticTestClass extends ChildStaticTestClass
{
    public static function getString()
    {
        return 'C' . parent::getString();
    }
}

class WithExitTestClass
{
    const RESULT = 42;
    public static $exit_called = false;
    public static $exit_code = true;

    public static function doWithExit()
    {
        $a = self::RESULT;
        $b = $a * 10;
        exit($b);
        return $a;
    }
}

abstract class WithoutConstantsTestClass
{
    //const A = 1;

    public function getA()
    {
        return static::A;
    }
}

class DescendantBaseTestClass
{
    public static function getDescendant()
    {
        return static::DESCENDANT;
    }
}

class DescendantFirstTestClass extends DescendantBaseTestClass
{
    const DESCENDANT = 20;
}

class ConstantRedeclareBaseTestClass
{
    public static function getBaseSelfValue()
    {
        return self::VALUE;
    }

    public static function getBaseStaticValue()
    {
        return static::VALUE;
    }
}

class ConstantRedeclareFirstTestClass extends ConstantRedeclareBaseTestClass
{
    const VALUE = 2;

    public static function getFirstParentValue()
    {
        return parent::VALUE;
    }

    public static function getFirstSelfValue()
    {
        return self::VALUE;
    }

    public static function getFirstStaticValue()
    {
        return static::VALUE;
    }
}

class ConstantRedeclareSecondTestClass extends ConstantRedeclareFirstTestClass
{
    public static function getSecondParentValue()
    {
        return parent::VALUE;
    }

    public static function getSecondSelfValue()
    {
        return self::VALUE;
    }

    public static function getSecondStaticValue()
    {
        return static::VALUE;
    }
}

class ConstantRedeclareThirdTestClass extends ConstantRedeclareSecondTestClass
{
    const VALUE = 4;

    public static function getThirdParentValue()
    {
        return parent::VALUE;
    }

    public static function getThirdSelfValue()
    {
        return self::VALUE;
    }

    public static function getThirdStaticValue()
    {
        return static::VALUE;
    }
}

class ConstantRedeclareForthTestClass extends ConstantRedeclareThirdTestClass
{
    public static function getForthParentValue()
    {
        return parent::VALUE;
    }

    public static function getForthSelfValue()
    {
        return self::VALUE;
    }

    public static function getForthStaticValue()
    {
        return static::VALUE;
    }
}

trait TraitA
{
    use TraitB;
}

trait TraitB
{
    public function doThings() {}
}

class ClassWithTraitA
{
    use TraitA;
}

class ClassWithTraitB
{
    use TraitB;
}

class ClassWithoutTraits
{
    public function doThings() {}
}

class ClassWithIsCallable
{
    public static function doThings() {}

    public static function check($callable)
    {
        if (is_callable($callable)) {
            return true;
        }
        return false;
    }
}

class ClassWithCallViaVariable
{
    public static function a()
    {
        return 1;
    }

    public static function callA()
    {
        return self::a();
    }

    public static function callAViaVariable()
    {
        $a = [self::class, 'a'];

        return $a();
    }
}

class ClassToTestPauseResume
{
    const A = 1;
    // const B = 2;
    const C = 3;
    
    const A_inside_pause = 1;
    // const B_inside_pause = 2;
    const C_inside_pause = 3;
    
    public function method()
    {
        return 1;
    }
    
    public function methodInsidePause()
    {
        return 1;
    }
    
    public function generator()
    {
        yield "a";
    }
    
    public function generatorInsidePause()
    {
        yield "a";
    }
}

function functionToTestPauseResume()
{
    return 1;
}

function functionToTestPauseResumeInsidePause()
{
    return 1;
}