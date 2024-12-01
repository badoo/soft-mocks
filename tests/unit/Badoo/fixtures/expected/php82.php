<?php

interface One{}



interface Two{}



interface Three{}



trait hasConstant{
    
    public const TRAIT_CONSTANT = 'from trait';}


readonly class doNotWriteMe{
    
    use \hasConstant;
    
    public function __construct(private string $readOnlyValue = self::TRAIT_CONSTANT){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(doNotWriteMe::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [$readOnlyValue];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}}}




class newTypeRules{
    
    public true $true = \true;
    protected false $false = \false;
    private null $null = \null;
    
    public function beConfused((\One&\Three)|\Two $value) : (\One&\Three)|\Two{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(newTypeRules::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [$value];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return $value;}
    
    
    public function getValue(bool $seed) : bool{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(newTypeRules::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [$seed];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return $this->getTruth($seed) || $this->getLie(!$seed);}
    
    
    protected function getTruth(true|null $seed) : true|null{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(newTypeRules::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [$seed];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return $seed;}
    
    
    private function getLie(false|null $seed) : false|null{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(newTypeRules::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [$seed];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return $seed;}}



enum A : string{
    
    case B = 'B';
    
    const C = [self::B?->value => self::B];}