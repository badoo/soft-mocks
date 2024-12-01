<?php
declare(strict_types=1);

namespace Badoo\SoftMock\Tests;

class ClassContainsPhpunitName
{
    public static function getBacktrace(): string
    {
        ob_start();
        \Badoo\SoftMocks::printBackTrace();
        return ob_get_clean();
    }
}
