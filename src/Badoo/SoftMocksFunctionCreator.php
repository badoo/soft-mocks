<?php
namespace Badoo;

class SoftMocksFunctionCreator
{
    public function run($obj, $class, $params, $mocks)
    {
        if ($mocks['code'] instanceof \Closure) {
            $new_func = $mocks['code'];
        } else {
            $code = "return function(" . $mocks['args'] . ") use (\$params) { " . $mocks['code'] . " };";
            $func = eval($code);
            $new_func = \Closure::bind($func, $obj, $class);
        }

        return call_user_func_array($new_func, $params);
    }
}
