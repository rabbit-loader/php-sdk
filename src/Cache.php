<?php

namespace RabbitLoader\SDK;

class Cache
{

    public const TTL_LONG = "long";
    public const TTL_SHORT = "short";

    private $request_url = '';
    private $fp_long = '';
    private $fp_short = '';
    private $debug = false;
    private $rootDir = '';
    private $file;

    public function __construct($request_url, $rootDir)
    {
        $this->request_url = $request_url;
        $this->rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR);

        if (!is_dir($this->rootDir)) {
            mkdir($this->rootDir, 0777, true);
        }

        $this->rootDir = $this->rootDir . DIRECTORY_SEPARATOR;
        $this->file  = new File();

        if (!is_dir($this->rootDir . self::TTL_LONG)) {
            if (!mkdir($this->rootDir . self::TTL_LONG, 0777, true)) {
                error_log("rabbitloader failed to create cache directory inside " . $this->rootDir);
            }
        }
        if (!is_dir($this->rootDir . self::TTL_SHORT)) {
            if (!mkdir($this->rootDir . self::TTL_SHORT, 0777, true)) {
                error_log("rabbitloader failed to create cache directory inside " . $this->rootDir);
            } else {
                //directory created successfully
                $this->addHtaccess();
            }
        }

        $hash = md5($this->request_url);
        $this->setPath($hash);
    }

    /**
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
        $this->file->setDebug($debug);
    }

    private function addHtaccess()
    {
        $loc = $this->rootDir . ".htaccess";
        if (!file_exists($loc)) {
            $content = "deny from all";
            $this->file->fpc($loc, $content);
        }

        return file_exists($loc);
    }
    public function exists($ttl, $shouldBeAfter = 0)
    {
        $fp = $ttl == self::TTL_LONG ? $this->fp_long . '_c' : $this->fp_short . '_c';
        $fe = file_exists($fp);
        if ($fe && $shouldBeAfter && $shouldBeAfter > 631152000) {
            $mt = filemtime($fp);
            if ($mt && $mt < $shouldBeAfter) {
                //post is modified after cache was generated
                $fe = false;
            }
        }
        return $fe;
    }

    public function delete($ttl)
    {
        $fp = $ttl == self::TTL_LONG ? $this->fp_long : $this->fp_short;
        $count = 0;
        $fc = $fp . '_c';
        $fh = $fp . '_h';

        if (is_file($fc) && (($this->debug && unlink($fc)) || @unlink($fc))) {
            $count++;
        }
        if (is_file($fh) && (($this->debug && unlink($fh)) || @unlink($fh))) {
            $count++;
        }
        return $count;
    }

    public function collectGarbage($mtime)
    {
        $lock = $this->rootDir . 'garbage.lock';
        if (!$this->file->lock($lock, $mtime)) {
            return;
        }
        $this->file->cleanDir($this->rootDir . self::TTL_LONG, 500, 30 * 24 * 3600);
        $this->file->cleanDir($this->rootDir . self::TTL_SHORT, 500, 1800);
    }

    public function deleteAll()
    {
        $count = 0;
        $count += $this->file->cleanDir($this->rootDir . self::TTL_LONG, 0, 0);
        $count += $this->file->cleanDir($this->rootDir . self::TTL_SHORT, 0, 0);
        return $count;
    }

    public function invalidate()
    {
        $fp = $this->fp_long . '_c';
        if (is_file($fp)) {
            $content = file_get_contents($fp);
            if ($content !== false) {
                $content = str_ireplace(['"rlCacheRebuild": "N"', '"rlCacheRebuild":"N"'], '"rlCacheRebuild": "Y"', $content);
                $re = '/const rlOriginalPageTime(.*);/U';
                $subst = 'const rlOriginalPage = new Date("' . date('c') . '");';
                $content = preg_replace($re, $subst, $content);
                $this->file->fpc($fp, $content);
            }
        }
    }


    public function &get($ttl, $type)
    {
        $fp = $ttl == self::TTL_LONG ? $this->fp_long : $this->fp_short;
        $content = '';
        if (file_exists($fp . '_' . $type)) {
            $content = file_get_contents($fp . '_' . $type);
        }
        return $content;
    }

    public function serve()
    {
        if ($this->exists(self::TTL_LONG)) {
            $content = file_get_contents($this->fp_long . '_c');
            if ($content !== false) {
                if ($this->valid($content)) {
                    if (file_exists($this->fp_long . '_h')) {
                        //header is optional
                        $this->sendHeaders(file_get_contents($this->fp_long . '_h'));
                    }
                    Util::sendHeader('x-rl-cache: hit', true);
                    echo $content;
                    return true;
                }
            }
        }
        return false;
    }

    public function save($ttl, &$content, &$headers)
    {
        $count = 0;
        if (!$this->valid($content)) {
            return $count;
        }
        $fp = $ttl == self::TTL_LONG ? $this->fp_long : $this->fp_short;
        $headers = json_encode($headers, JSON_INVALID_UTF8_IGNORE);

        if ($this->file->fpc($fp . '_h', $headers)) {
            $count++;
        }
        if ($this->file->fpc($fp . '_c', $content)) {
            $count++;
        }
        return $count;
    }

    private function valid(&$chunk)
    {
        if (empty($chunk)) {
            return false;
        }
        if (stripos($chunk, '</html>') !== false || stripos($chunk, '</body>') !== false) {
            return true;
        }
        return false;
    }

    private function sendHeaders($headers)
    {
        $headers_sent = 0;
        if (empty($headers)) {
            return $headers_sent;
        }
        if (!is_array($headers)) {
            $headers_decoded = json_decode($headers, true);
            if ($headers_decoded === false) {
                $e = new \Error(json_last_error_msg());
                Exc:: catch($e, $headers);
            } else {
                $headers = $headers_decoded;
            }
        }
        if (!empty($headers)) {
            foreach ($headers as $key => $values) {
                foreach ($values as $val) {
                    header($key . ':' . $val, false);
                    $headers_sent++;
                }
            }
        }
        return $headers_sent;
    }

    public function setPath($hash)
    {
        $this->fp_long =  $this->rootDir . self::TTL_LONG . DIRECTORY_SEPARATOR . $hash;
        $this->fp_short =  $this->rootDir . self::TTL_SHORT . DIRECTORY_SEPARATOR . $hash;
    }

    public function setVariant($variant)
    {
        if (empty($variant) || !is_array($variant)) {
            return;
        }
        ksort($variant);
        $hash = md5($this->request_url . json_encode($variant));
        $this->setPath($hash);
    }

    public function getCacheCount()
    {
        return $this->file->countFiles($this->rootDir . self::TTL_LONG);
    }
}
