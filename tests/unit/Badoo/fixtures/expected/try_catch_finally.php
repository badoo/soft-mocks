<?php

try {
    $a = isset(\Badoo\SoftMocks::$func_mocks_by_name['replaceSomething']) ? \Badoo\SoftMocks::callFunction('', 'replaceSomething', ["something"]) : \replaceSomething("something");} catch (\Exception $e) {
    
    echo $e->getMessage();} finally {
    
    echo "finally";}