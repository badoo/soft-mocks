<?php
$function = function($input) {
    return "!$input!";
};

$f2 = 'sprintf';

return "{$function('x')}:{$f2('?%s?', 'x')}";
