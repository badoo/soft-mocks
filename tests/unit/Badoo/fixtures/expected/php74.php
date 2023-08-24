<?php


class User{
    
    public int $id;
    public string $name;}



$factor = 10;
$nums = isset(\Badoo\SoftMocks::$func_mocks_by_name['array_map']) ? \Badoo\SoftMocks::callFunction('', 'array_map', [fn($n) => $n * (isset(\Badoo\SoftMocks::$func_mocks_by_name['pow']) ? \Badoo\SoftMocks::callFunction('', 'pow', [2, $factor]) : \pow(2, $factor)), [1, 2, 3, 4]]) : \array_map(fn($n) => $n * (isset(\Badoo\SoftMocks::$func_mocks_by_name['pow']) ? \Badoo\SoftMocks::callFunction('', 'pow', [2, $factor]) : \pow(2, $factor)), [1, 2, 3, 4]);


$array['key'] ??= isset(\Badoo\SoftMocks::$func_mocks_by_name['pow']) ? \Badoo\SoftMocks::callFunction('', 'pow', [2, 3]) : \pow(2, 3);


$parts = ['apple', 'pear'];

function convert(array $a){
    return isset(\Badoo\SoftMocks::$func_mocks_by_name['array_reverse']) ? \Badoo\SoftMocks::callFunction('', 'array_reverse', [$a]) : \array_reverse($a);}


$fruits = [
    'banana',
    ...isset(\Badoo\SoftMocks::$func_mocks_by_name['convert']) ? \Badoo\SoftMocks::callFunction('', 'convert', [&$parts]) : \convert($parts),
    'watermelon',
];