<?php

class NamedArgumentsTestClass {
    public function __construct(bool $arg1, float $arg2) {}
    static function staticMethod(int $arg1, string $arg2) {}
    function method(int $arg1, string $arg2) {}
}
/** @noinspection MkdirRaceConditionInspection */
mkdir('/tmp/newdir', recursive: true);

NamedArgumentsTestClass::staticMethod(0, arg2: "abc");

$obj = new NamedArgumentsTestClass(false,
    arg2: 1.5);

$obj->method(5, arg2: "new arg");