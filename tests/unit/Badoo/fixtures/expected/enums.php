<?php

interface HasColor{
    
    public function color() : string;}


enum Status implements \HasColor{
    
    
    case DRAFT;
    
    /**
 * But this one will stay
 */
    case PUBLISHED;
    case ARCHIVED;
    
    public static function getDefault() : self{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(self::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return isset(\Badoo\SoftMocks::$class_const_mocks_by_name['DRAFT']) ? \Badoo\SoftMocks::getClassConst(self::class, 'DRAFT', null) : self::DRAFT;}
    
    
    public function color() : string{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(self::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return match ($this) {
            isset(\Badoo\SoftMocks::$class_const_mocks_by_name['DRAFT']) ? \Badoo\SoftMocks::getClassConst(self::class, 'DRAFT', null) : self::DRAFT => 'grey',
            isset(\Badoo\SoftMocks::$class_const_mocks_by_name['PUBLISHED']) ? \Badoo\SoftMocks::getClassConst(\Status::class, 'PUBLISHED', null) : \Status::PUBLISHED => 'green',
            isset(\Badoo\SoftMocks::$class_const_mocks_by_name['ARCHIVED']) ? \Badoo\SoftMocks::getClassConst(\Status::class, 'ARCHIVED', null) : \Status::ARCHIVED => 'red',
        };}}



enum StatusWithValue : string implements \HasColor{
    
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
    
    public function color() : string{if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(self::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        return 'blue';}}



enum StatusWithNumber : int{
    
    case DRAFT = 1;
    case PUBLISHED = 2;
    case ARCHIVED = 3;}


class BlogPost{
    
    private string $color;
    
    public function __construct(public ?\Status $status = \null){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(BlogPost::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [$status];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        $this->color = ($this->status ?? \Status::getDefault())->color();}}



class MainTest{
    
    private \BlogPost $blogPost;
    
    public function __construct(){if (isset(\Badoo\SoftMocks::$mocks_by_name[__FUNCTION__]) && false !== $__softmocksvariableforcode = \Badoo\SoftMocks::isMocked(MainTest::class, static::class, __FUNCTION__)) {$mm_func_args = func_get_args();$params = [];$variadic_params_idx = '';return eval($__softmocksvariableforcode);/** @codeCoverageIgnore */}
        
        $this->blogPost = new \BlogPost(isset(\Badoo\SoftMocks::$class_const_mocks_by_name['DRAFT']) ? \Badoo\SoftMocks::getClassConst(\Status::class, 'DRAFT', self::class) : \Status::DRAFT);}}
