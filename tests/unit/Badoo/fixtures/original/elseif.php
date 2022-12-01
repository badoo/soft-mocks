<?php

function foo() {
    if ($foo) {
        return $bar;
    } elseif (bar()) {
        baz();
    } elseif (bar2()) {
        baz2();
    } else {
        baz3();
    }

    return null;
}