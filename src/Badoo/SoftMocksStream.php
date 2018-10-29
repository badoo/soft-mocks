<?php
namespace Badoo;

class SoftMocksStream
{
    public $context;
    private $fp;
    private $filename;

    public function stream_close()
    {
        fclose($this->fp);
    }

    public function stream_eof()
    {
        return feof($this->fp);
    }

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $this->filename = $path;

        if (mb_orig_strpos($path, "soft://") === 0) {
            $path = mb_orig_substr($path, mb_orig_strlen("soft://"));
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
        return stat($path, $flags);
    }
}
