<?php
$function = function($input) {
    return "!$input!";
};

$f2 = 'sprintf';

$str = "{$function('x')}:{$f2('?%s?', 'x')}" . "{$f2('?%s?', 'x')}";
return "{$function('x')}:{$f2('?%s?', 'x')}";
