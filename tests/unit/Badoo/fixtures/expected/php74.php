<?php


class User{
    
    public int $id;
    public string $name;}



$factor = 10;
$nums = \Badoo\SoftMocks::callFunction('', 'array_map', [fn($n) => $n * \Badoo\SoftMocks::callFunction('', 'pow', [2, $factor]), [1, 2, 3, 4]]);


$array['key'] ??= \Badoo\SoftMocks::callFunction('', 'pow', [2, 3]);


$parts = ['apple', 'pear'];

function convert(array $a){
    return \Badoo\SoftMocks::callFunction('', 'array_reverse', [$a]);}


$fruits = [
    'banana',
    ...\Badoo\SoftMocks::callFunction('', 'convert', [&$parts]),
    'watermelon',
];