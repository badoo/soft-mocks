<?php

use Foo\Bar\{Baz1, Baz2};

function a($cnt){
    
    return \Badoo\SoftMocks::callFunction('', 'str_pad', ['a', \Badoo\SoftMocks::callFunction('', 'intval', [$cnt])]);}


function o() : object{
    
    $o = new \stdClass();
    $o->value = \Badoo\SoftMocks::callFunction('', 'a', [1]);
    return $o;}


echo 'end';