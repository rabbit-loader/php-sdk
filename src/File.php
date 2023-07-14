<?php

namespace RabbitLoader\SDK;

class File
{
    private $debug = false;

    public function __construct()
    {
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;
    }


    public function fpc($fp, &$data)
    {
        if ($this->debug) {
            $file_updated = file_put_contents($fp, $data, LOCK_EX);
        } else {
            $file_updated = @file_put_contents($fp, $data, LOCK_EX);
        }
        if (!$file_updated && $this->debug) {
            throw new \Exception("could not write file $fp");
        }
        return $file_updated;
    }

    public function cleanDir($dir, $max_limit, $offsetSec)
    {
        $dir = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*';
        $isBefore = time() - $offsetSec;
        $deleted_count = 0;
        $files = glob($dir); // get all file names
        foreach ($files as $file) {
            $delete = is_file($file);
            if ($offsetSec && filemtime($file) > $isBefore) {
                $delete = false;
            }
            if ($delete && @unlink($file)) {
                ++$deleted_count;
                if ($max_limit > 0 && $deleted_count > $max_limit) {
                    break;
                }
            }
        }
        return $deleted_count;
    }

    public function lock($fp, $mtime)
    {
        try {
            if (file_exists($fp) && filemtime($fp) > $mtime) {
                return false;
            }
            return touch($fp);
        } catch (\Throwable $e) {
            Exc:: catch($e);
        } catch (\Exception $e) {
            Exc:: catch($e);
        }
    }
}
