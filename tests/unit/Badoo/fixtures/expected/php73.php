<?php

$array = [1, 2];
list($a, &$b) = $array;

$parts = isset(\Badoo\SoftMocks::$func_mocks_by_name['explode']) ? \Badoo\SoftMocks::callFunction('', 'explode', [",", isset(\Badoo\SoftMocks::$func_mocks_by_name['implode']) ? \Badoo\SoftMocks::callFunction('', 'implode', [",", ["a", "b", "c"]]) : \implode(
    ",",
    ["a", "b", "c"])]) : \explode(
    ",",
    isset(\Badoo\SoftMocks::$func_mocks_by_name['implode']) ? \Badoo\SoftMocks::callFunction('', 'implode', [",", ["a", "b", "c"]]) : \implode(
        ",",
        ["a", "b", "c"]));

function h($cnt){
    
    return isset(\Badoo\SoftMocks::$func_mocks_by_name['str_repeat']) ? \Badoo\SoftMocks::callFunction('', 'str_repeat', [<<<HERE
    123 {$cnt}
    HERE . (isset(\Badoo\SoftMocks::$func_mocks_by_name['chr']) ? \Badoo\SoftMocks::callFunction('', 'chr', [123]) : \chr(123)), isset(\Badoo\SoftMocks::$func_mocks_by_name['intval']) ? \Badoo\SoftMocks::callFunction('', 'intval', [$cnt]) : \intval($cnt)]) : \str_repeat(
        <<<HERE
        123 {$cnt}
        HERE . (isset(\Badoo\SoftMocks::$func_mocks_by_name['chr']) ? \Badoo\SoftMocks::callFunction('', 'chr', [123]) : \chr(123)),
        isset(\Badoo\SoftMocks::$func_mocks_by_name['intval']) ? \Badoo\SoftMocks::callFunction('', 'intval', [$cnt]) : \intval($cnt));}



function n($cnt){
    
    return isset(\Badoo\SoftMocks::$func_mocks_by_name['str_repeat']) ? \Badoo\SoftMocks::callFunction('', 'str_repeat', [<<<'NOW'
    123 {$cnt}
    NOW . (isset(\Badoo\SoftMocks::$func_mocks_by_name['chr']) ? \Badoo\SoftMocks::callFunction('', 'chr', [123]) : \chr(123)), isset(\Badoo\SoftMocks::$func_mocks_by_name['intval']) ? \Badoo\SoftMocks::callFunction('', 'intval', [$cnt]) : \intval($cnt)]) : \str_repeat(
        <<<'NOW'
        123 {$cnt}
        NOW . (isset(\Badoo\SoftMocks::$func_mocks_by_name['chr']) ? \Badoo\SoftMocks::callFunction('', 'chr', [123]) : \chr(123)),
        isset(\Badoo\SoftMocks::$func_mocks_by_name['intval']) ? \Badoo\SoftMocks::callFunction('', 'intval', [$cnt]) : \intval($cnt));}



echo 'end';
