<?php

interface One{}



interface Two{}



interface Three{}



function something(...$arguments){}



#[\TestAttribute(new \stdClass())]
class testThings{
    
    public final const CANNOT_OVERRIDE = 'the final word';
    
    public function __construct(public readonly \One&\Three $typeIntersection, protected readonly string $valueIsReadOnly = 'only read', private readonly \stdClass $canInitializeNewClassHere = new \stdClass()){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(testThings::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$typeIntersection, $valueIsReadOnly, $canInitializeNewClassHere];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}}
    
    
    
    
    
    
    public function testStoppingProgramFlow(){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(testThings::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        $value = 1 + 2;
        
        \Badoo\SoftMocks::callExit(666);}
    
    
    public function testMakeFunctionClosureWithSpreadOperator(\One&\Three $typeIntersection) : void{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(testThings::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$typeIntersection];$variadic_params_idx = '';eval($__softmocksvariableforcode);return;/** @codeCoverageIgnore */}
        
        $closureOne = isset(\Badoo\SoftMocks::$func_mocks_by_name['array_merge']) ? fn() => \Badoo\SoftMocks::callFunction('', 'array_merge', \func_get_args()) : \array_merge(...);
        $closureTwo = isset(\Badoo\SoftMocks::$func_mocks_by_name['something']) ? fn() => \Badoo\SoftMocks::callFunction('', 'something', \func_get_args()) : \something(...);
        $closureThree = $this->testStoppingProgramFlow(...);
        
        \Badoo\SoftMocks::call(__NAMESPACE__, $closureOne, [['will'], ['be'], ['merged'], ['some new octal notation:', 016, 033]]);
        \Badoo\SoftMocks::call(__NAMESPACE__, $closureTwo, [&$typeIntersection, &$typeIntersection]);}}



function testTypeIntersectionInFunction(\One&\Three $typeIntersection){}



function neverReturns(){
    
    \Badoo\SoftMocks::callExit(111);}