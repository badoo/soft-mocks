<?php
/**
 * @author Kirill Abrosimov <k.abrosimov@corp.badoo.com>
 * @author Rinat Akhmadeev <r.akhmadeev@corp.badoo.com>
 */
namespace QA;

class DefaultTestClass
{
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
