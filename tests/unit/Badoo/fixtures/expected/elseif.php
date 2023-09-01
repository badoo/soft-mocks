<?php

function foo(){
    if ($foo) {
        return $bar;} 
        elseif (\Badoo\SoftMocks::callFunction('', 'bar', [])) {
            \Badoo\SoftMocks::callFunction('', 'baz', []);}
        elseif (\Badoo\SoftMocks::callFunction('', 'bar2', [])) {
            \Badoo\SoftMocks::callFunction('', 'baz2', []);} else {
        
        \Badoo\SoftMocks::callFunction('', 'baz3', []);}
    
    
    return \null;}