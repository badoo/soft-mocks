<?php

function foo(){
    if ($foo) {
        return $bar;} 
        elseif (\Badoo\SoftMocks::callFunction(__NAMESPACE__, 'bar', [])) {
            \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'baz', []);}
        elseif (\Badoo\SoftMocks::callFunction(__NAMESPACE__, 'bar2', [])) {
            \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'baz2', []);} else {
        
        \Badoo\SoftMocks::callFunction(__NAMESPACE__, 'baz3', []);}
    
    
    return \null;}