<?php

$dispatcherArgumentsJson = \Badoo\SoftMocks::callRewrittenOrOriginalFunction('', 'json_encode', [[

    'first element',
    (isset(\Badoo\SoftMocks::$func_mocks_by_name['getcwd']) ? \Badoo\SoftMocks::callFunction('', 'getcwd', []) : \getcwd()) ?: 'no cwd',
    'third element',
    isset(\Badoo\SoftMocks::$func_mocks_by_name['trim']) ? \Badoo\SoftMocks::callFunction('', 'trim', [isset(\Badoo\SoftMocks::$func_mocks_by_name['str_repeat']) ? \Badoo\SoftMocks::callFunction('', 'str_repeat', ['hey! ', 10]) : \str_repeat('hey! ', 10)]) : \trim(isset(\Badoo\SoftMocks::$func_mocks_by_name['str_repeat']) ? \Badoo\SoftMocks::callFunction('', 'str_repeat', ['hey! ', 10]) : \str_repeat('hey! ', 10)),
    'last element',
]]);



isset(\Badoo\SoftMocks::$func_mocks_by_name['file_exists']) ? \Badoo\SoftMocks::callFunction('', 'file_exists', ['x']) : \file_exists('x');
