<?php

try {
    $a = \Badoo\SoftMocks::callFunction('', 'replaceSomething', ["something"]);} catch (\Exception $e) {
    
    echo $e->getMessage();} finally {
    
    echo "finally";}