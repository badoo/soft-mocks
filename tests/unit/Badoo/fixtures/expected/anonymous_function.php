<?php
$function = function ($input) {
    return "!{$input}!";};


$f2 = 'sprintf';

return \Badoo\SoftMocks::call(
    __NAMESPACE__,
    $function,
    ['x']) . ":" . \Badoo\SoftMocks::call(
    __NAMESPACE__,
    $f2,
    ['?%s?', 'x']);
