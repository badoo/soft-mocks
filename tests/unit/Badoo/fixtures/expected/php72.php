<?php

use Foo\Bar\{Baz1, Baz2};

function a($cnt){
    
    return isset(\Badoo\SoftMocks::$func_mocks_by_name['str_pad']) ? \Badoo\SoftMocks::callFunction('', 'str_pad', ['a', isset(\Badoo\SoftMocks::$func_mocks_by_name['intval']) ? \Badoo\SoftMocks::callFunction('', 'intval', [$cnt]) : \intval($cnt)]) : \str_pad('a', isset(\Badoo\SoftMocks::$func_mocks_by_name['intval']) ? \Badoo\SoftMocks::callFunction('', 'intval', [$cnt]) : \intval($cnt));}


function o() : object{
    
    $o = new \stdClass();
    $o->value = isset(\Badoo\SoftMocks::$func_mocks_by_name['a']) ? \Badoo\SoftMocks::callFunction('', 'a', [1]) : \a(1);
    return $o;}


echo 'end';