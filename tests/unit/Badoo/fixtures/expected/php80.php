<?php

class NewTypes{
    public int|float $intOrFloatPublicProperty;
    protected int|float $intOrFloatProtectedProperty;
    private int|float $intOrFloatPrivateProperty;
    
    public function intOrFloatArgument(int|float $intOrFloat) : void{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(NewTypes::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$intOrFloat];$variadic_params_idx = '';eval($__softmocksvariableforcode);return;/** @codeCoverageIgnore */}
        return;}
    
    
    public function intOrFloatResult() : int|float{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(NewTypes::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        return 1.0;}
    
    
    public function intOrFloatArgumentAndResult(int|float $intOrFloat) : int|float{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(NewTypes::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$intOrFloat];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        return $intOrFloat;}
    
    
    public function mixedArgumentAndResult(mixed $mixed) : mixed{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(NewTypes::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$mixed];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        return $mixed ?? $this->intOrFloatResult();}}



function intOrFloatArgumentAndResult(int|float $intOrFloat) : int|float{
    return $intOrFloat;}


function nullsafeUsagee(){
    function returnNull(){
        return \null;}
    
    
    return (isset(\Badoo\SoftMocks::$func_mocks_by_name['returnNull']) ? \Badoo\SoftMocks::callFunction('', 'returnNull', []) : \returnNull())?->test();}


function multipleArguments($arg1, ?int $arg2 = \null, int $arg3 = 1) : void{}


isset(\Badoo\SoftMocks::$func_mocks_by_name['multipleArguments']) ? \Badoo\SoftMocks::callFunction('', 'multipleArguments', ['arg1' => 'arg1', 'arg3' => 10]) : \multipleArguments(arg1: 'arg1', arg3: 10);

#[\Attribute]class TestAttribute{
    
    
    public const TEST_VALUE = 'test-value';
    private string $event;
    
    public function __construct(string $event){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(TestAttribute::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$event];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        $this->event = $event;}}



#[\TestAttribute('event')]class TestAttributeUser1{
    
    
    public function foo() : void{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(TestAttributeUser1::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';eval($__softmocksvariableforcode);return;/** @codeCoverageIgnore */}}}


#[\TestAttribute('event'), \TestAttribute('event2')]class TestAttributeUser2{
    
    
    
    
    
    public function foo() : void{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(TestAttributeUser2::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';eval($__softmocksvariableforcode);return;/** @codeCoverageIgnore */}}
    
    
        
        #[\TestAttribute('event3')]protected function bar(#[\TestAttribute('event4')] $bar) : void{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(TestAttributeUser2::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$bar];$variadic_params_idx = '';eval($__softmocksvariableforcode);return;/** @codeCoverageIgnore */}}
    
        #[\TestAttribute(\TestAttribute::TEST_VALUE)]protected function bar(#[\TestAttribute(\TestAttribute::TEST_VALUE)] $bar) : void{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(TestAttributeUser2::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$bar];$variadic_params_idx = '';eval($__softmocksvariableforcode);return;/** @codeCoverageIgnore */}}}


function matchTest(int $input) : string{
    
    return match ($input) {
        415 => 'teapot!',
        default => 'mkay',
    };}


class ConstructorPropertyPromotion{
    
    public function __construct(public int $publicInt, protected int $protectedInt, private int $privateInt){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(ConstructorPropertyPromotion::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$publicInt, $protectedInt, $privateInt];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}}}







class StaticReturn{
    
    public static function instance() : static{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(StaticReturn::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        return new static();}
    
    
    public static function getClassName() : string{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(StaticReturn::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        $obj = static::instance();
        return $obj::class;}}



function throwExpression(mixed $input){
    return $input ?? throw new \Exception();}


try {
    $foo = 'bar';} catch (\Exception) {
    
    $foo = 'baz';}