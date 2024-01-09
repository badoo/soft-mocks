<?php

class NamedArgumentsTestClass{
    public function __construct(bool $arg1, float $arg2){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(NamedArgumentsTestClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$arg1, $arg2];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}}
    static function staticMethod(int $arg1, string $arg2){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(NamedArgumentsTestClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$arg1, $arg2];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}}
    function method(int $arg1, string $arg2){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(NamedArgumentsTestClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$arg1, $arg2];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}}}

/** @noinspection MkdirRaceConditionInspection */
isset(\Badoo\SoftMocks::$func_mocks_by_name['mkdir']) ? \Badoo\SoftMocks::callFunction('', 'mkdir', ['/tmp/newdir', 'recursive' => \true]) : \mkdir('/tmp/newdir', recursive: \true);

\NamedArgumentsTestClass::staticMethod(0, arg2: "abc");

$obj = new \NamedArgumentsTestClass(
    \false,
    arg2: 1.5);
$obj->method(5, arg2: "new arg");