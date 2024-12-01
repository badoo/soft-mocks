<?php
/**
 * This file contains php71 code
 */
class SomeClass{
    
    protected const VALUES = ['a', 'b', 'c'];
    
    public static function getAB(){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(SomeClass::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        [
            $a,
            ,
            $b,] = isset(\Badoo\SoftMocks::$class_const_mocks_by_name['VALUES']) ? \Badoo\SoftMocks::getClassConst(self::class, 'VALUES', self::class) : self::VALUES;
        
        return [$a, $b];}}