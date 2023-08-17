<?php

/**
 * This file contains php7 code
 */
function replaceSomething($string) : string{
    
    
    
    return \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'str_replace', ['something', 'somebody', $string]);}


class SomeClass{
    
    const E = \M_E;
    public $a = 1;
    
    public function methodReturn() : string{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return self::methodSelf("string");}
    
    
    protected static function methodSelf($string) : string{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$string];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'replaceSomething', [&$string]);}
    
    
    public function methodParam(string $string){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$string];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return $string;}
    
    
    public function methodNullableParam(?string $string){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$string];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return $string;}
    
    
    public function methodNullableReturn() : ?array{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return \null;}
    
    
    public function methodVoidReturn() : void{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';eval($__softmocksvariableforcode);return;/** @codeCoverageIgnore */}
        
        echo "something";}
    
    
    public function methodNullableParamReturn(?string $string) : string{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$string];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return $string ?? "string";}
    
    
    public function methodParamNullableReturn(string $string) : ?string{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$string];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return $string ? $string : \null;}
    
    
    public function methodNullableParamNullableReturn(?string $string) : ?string{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$string];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return $string;}
    
    
    public function methodWithOnlyVariadicParams(...$args){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$args];$variadic_params_idx = '0';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'sizeof', [$args]);}
    
    
    public function methodWithDifferentParamsTypes($a, $b, ...$args){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [$a, $b, $args];$variadic_params_idx = '2';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return $a . $b . \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'sizeof', [$args]);}
    
    
    public static function methodWithNamedFunctionDefinition(){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== ($__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__))) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        function SomeClass_methodWithNamedFunctionDefinition(){
            
            return isset(\Badoo\SoftMocks::$class_const_mocks_by_name['E']) ? \Badoo\SoftMocks::getClassConst(\SomeClass::class, 'E', null) : \SomeClass::E;}
        
        return \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'SomeClass_methodWithNamedFunctionDefinition', []);}}