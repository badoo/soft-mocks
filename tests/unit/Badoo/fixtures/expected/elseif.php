<?php

function foo(){
    if ($foo) {
        return $bar;} 
        elseif (isset(\Badoo\SoftMocks::$func_mocks_by_name['bar']) ? \Badoo\SoftMocks::callFunction('', 'bar', []) : \bar()) {
            isset(\Badoo\SoftMocks::$func_mocks_by_name['baz']) ? \Badoo\SoftMocks::callFunction('', 'baz', []) : \baz();}
        elseif (isset(\Badoo\SoftMocks::$func_mocks_by_name['bar2']) ? \Badoo\SoftMocks::callFunction('', 'bar2', []) : \bar2()) {
            isset(\Badoo\SoftMocks::$func_mocks_by_name['baz2']) ? \Badoo\SoftMocks::callFunction('', 'baz2', []) : \baz2();} else {
        
        isset(\Badoo\SoftMocks::$func_mocks_by_name['baz3']) ? \Badoo\SoftMocks::callFunction('', 'baz3', []) : \baz3();}
    
    
    return \null;}