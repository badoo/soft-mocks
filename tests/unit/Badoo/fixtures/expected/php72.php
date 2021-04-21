<?php

use Foo\Bar\{Baz1, Baz2};

function a($cnt){
    
    return \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'str_pad', ['a', \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'intval', [$cnt])]);}


function o() : object{
    
    $o = new \stdClass();
    $o->value = \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'a', [1]);
    return $o;}


echo 'end';
