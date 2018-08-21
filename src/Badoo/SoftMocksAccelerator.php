<?php

namespace Badoo;

use Composer\XdebugHandler\XdebugHandler;

class SoftMocksAccelerator
{
    public static function warmup($file, $max_cores)
    {
        $xdebug = new XdebugHandler('myapp');
        $xdebug->check();
        unset($xdebug);

        $old_files = get_included_files();
        require $file;
        $new_files = array_diff(get_included_files(), $old_files);
        self::warmupList($new_files, $max_cores);
    }

    public static function warmupList($new_files, $max_cores)
    {
        usort(
            $new_files,
            function($a, $b) {
                $a_size = filesize($a);
                $b_size = filesize($b);
                return $b_size - $a_size;
            }
        );

        $lists = [];
        $sums = [];

        for ($i = 0; $i < $max_cores; $i++) {
            $lists[$i] = [];
            $sums[$i] = 0;
        }

        foreach ($new_files as $f) {
            asort($sums, SORT_NUMERIC);
            $size = filesize($f);
            $cur_shard = key($sums);
            $lists[$cur_shard][] = $f;
            $sums[$cur_shard] += $size;
        }

        for ($i = 0; $i < $max_cores; $i++) {
            $pid = pcntl_fork();
            if ($pid < 0) {
                die("Could not fork");
            } else if ($pid == 0) {
                foreach ($lists[$i] as $f) {
                    fwrite(STDOUT, "\033[2KRewriting $f (" . round(filesize($f) / 1024, 1) . " KiB)\r");
                    try {
                        SoftMocks::doRewrite($f);
                    } catch (\Exception $e) {
                        fwrite(STDERR, "\nCould not rewrite file $f: " . $e->getMessage() . "\n");
                    }
                }
                exit(0);
            }
        }

        for ($i = 0; $i < $max_cores; $i++) {
            pcntl_wait($status);
        }
    }
}
