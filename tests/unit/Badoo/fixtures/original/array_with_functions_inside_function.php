<?php

$dispatcherArgumentsJson = json_encode(
    [
        'first element',
        getcwd() ?: 'no cwd',
        'third element',
        trim(str_repeat('hey! ', 10)),
        'last element'
    ],
);

file_exists('x');
