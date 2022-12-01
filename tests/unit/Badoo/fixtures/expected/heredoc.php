<?php

$var1 = <<<var1
test
var1;

$var2 = <<<var2
test
var2;


$var3 = <<<'var3'
test
var3;

$var4 = <<<'var4'
test
var4;


function getDescription() : string{
    
    return <<<Description
Test
Description;}


\Badoo\SoftMocks::callFunction(__NAMESPACE__, 'var_dump', ['test']);