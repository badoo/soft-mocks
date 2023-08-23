<?php

$array = [1, 2];
list($a, &$b) = $array;

$parts = \Badoo\SoftMocks::callFunction('', 'explode', [",", \Badoo\SoftMocks::callFunction('', 'implode', [",", ["a", "b", "c"]])]);







function h($cnt){
    
    return \Badoo\SoftMocks::callFunction('', 'str_repeat', [<<<HERE
123 {$cnt}
HERE
 . \Badoo\SoftMocks::callFunction('', 'chr', [123]), \Badoo\SoftMocks::callFunction('', 'intval', [$cnt])]);}







function n($cnt){
    
    return \Badoo\SoftMocks::callFunction('', 'str_repeat', [<<<'NOW'
123 {$cnt}
NOW
 . \Badoo\SoftMocks::callFunction('', 'chr', [123]), \Badoo\SoftMocks::callFunction('', 'intval', [$cnt])]);}







echo 'end';