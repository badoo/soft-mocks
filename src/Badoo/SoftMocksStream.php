<?php
namespace Badoo;

class SoftMocksStream
{
    public $context;
    private $fp;
    private $dh;

    public function dir_opendir($path, $options)
    {
        stream_wrapper_restore("file");
        $this->dh = opendir($path);
        stream_wrapper_unregister("file");
        stream_wrapper_register("file", self::class);

        return $this->dh !== false;
    }

    public function dir_readdir()
    {
        return readdir($this->dh);
    }

    public function dir_rewinddir()
    {
        return rewinddir($this->dh);
    }

    public function dir_closedir()
    {
        return closedir($this->dh);
    }

    public function stream_close()
    {
        fclose($this->fp);
    }

    public function stream_eof()
    {
        return feof($this->fp);
    }

    public function stream_cast($cast_as) {
        if ($this->fp) {
            return $this->fp;
        }
        if ($this->dh) {
            return $this->dh;
        }
        return false;
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        // magic
        stream_wrapper_restore("file");

        if (mb_orig_strpos($path, "soft://") === 0) {
            $path = mb_orig_substr($path, mb_orig_strlen("soft://"));
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ($ext !== 'php' && $ext !== 'inc' && $ext !== 'phtml' && $ext !== 'php5') {
            $this->fp = fopen($path, $mode);
            return $this->fp !== false;
        }

        try {
            $rewritten = SoftMocks::doRewrite($path, $opened_path);

            if ($options & STREAM_REPORT_ERRORS == STREAM_REPORT_ERRORS) {
                $this->fp = fopen($rewritten, $mode);
            } else {
                $this->fp = @fopen(SoftMocks::doRewrite($path), $mode);
            }
        } catch (\Exception $e) {
            fwrite(STDERR, "Could not rewrite file $path: " . $e->getMessage() . "\n");
            return false;
        }

        return $this->fp !== false;
    }

    public function stream_read($count)
    {
        return fread($this->fp, $count);
    }

    public function stream_seek($offset, $whence = SEEK_SET)
    {
        return fseek($this->fp, $offset, $whence);
    }

    public function stream_stat()
    {
        return fstat($this->fp);
    }

    public function stream_tell()
    {
        return ftell($this->fp);
    }

    public function url_stat($path, $flags)
    {
        stream_wrapper_restore("file");
        // not the best solution, but $flags does not always specify that we do not need errors
        $res = @stat($path);
        stream_wrapper_unregister("file");
        stream_wrapper_register("file", self::class);
        return $res;
    }
}
