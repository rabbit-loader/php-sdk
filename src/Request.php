<?php

namespace RabbitLoader\SDK;

class Request
{
    private string $licenseKey = '';
    private $requestURL = "";
    private $requestURI = "";
    private Cache $cacheFile;
    private bool $debug = false;
    private string $rootDir = '';
    private const IG_PARAMS = ['_gl', 'epik', 'fbclid', 'gbraid', 'gclid', 'msclkid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'vgo_ee', 'wbraid', 'zenid', 'rltest', 'rlrand'];
    private bool $ignoreRead = false;
    private bool $ignoreWrite = false;
    private string $ignoreReason = 'default';
    private bool $isNoOptimization = false;
    private bool $isWarmup = false;
    private int $onlyAfter = 0;
    private $purgeCallback = null;

    public function __construct($licenseKey, $rootDir)
    {
        $this->licenseKey = $licenseKey;
        $this->rootDir = $rootDir;
        $this->parse();
        $this->ignoreParams(self::IG_PARAMS);
        $this->cacheFile = new Cache($this->getURL(), $this->rootDir);
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;
        $this->cacheFile->setDebug($this->debug);
    }

    public function getURL()
    {
        return $this->requestURL;
    }

    public function ignoreRequest($reason = '')
    {
        $this->ignoreRead = true;
        $this->ignoreWrite = true;
        $this->ignoreReason = $reason;
        Util::sendHeader('x-rl-skip: ' . $this->ignoreReason, true);
    }

    public function skipForCookies($cookieNames)
    {
        if (!empty($cookieNames)) {
            foreach ($cookieNames as $c) {
                if (isset($_COOKIE[$c])) {
                    $this->ignoreRequest("cookie-$c");
                    break;
                }
            }
        }
    }

    public function skipForPaths($patterns)
    {
        if (!empty($patterns)) {
            foreach ($patterns as $i => $path_pattern) {
                if (!empty($path_pattern)) {
                    $matched = fnmatch(trim($path_pattern), $this->requestURI);
                    if (!$matched) {
                        $matched = fnmatch(trim($path_pattern), rawurldecode($this->requestURI));
                    }
                    if (!$matched) {
                        $matched = fnmatch($path_pattern, rawurldecode($this->requestURI));
                    }
                    if ($matched) {
                        $this->ignoreRequest("skip-path-$path_pattern");
                        break;
                    }
                }
            }
        }
    }

    public function ignoreParams($paramNames)
    {
        if (empty($paramNames)) {
            return;
        }
        $parsed_url = parse_url($this->requestURL);
        $query = empty($parsed_url['query']) ? '' : trim($parsed_url['query']);
        if (!empty($query)) {
            try {
                parse_str($query, $qs_vars);

                if (!empty($paramNames)) {
                    foreach ($paramNames as $p) {
                        unset($qs_vars[trim($p)]);
                    }
                }

                $query = http_build_query($qs_vars);

                $this->requestURI = trim(@$parsed_url['path']) . (empty($query) ? '' : '?' . $query);;
                $host = trim(@$parsed_url['host']);
                $scheme = trim(@$parsed_url['scheme']);
                $this->requestURL = $scheme . '://' . $host . $this->requestURI;
            } catch (\Throwable $e) {
                Exc:: catch($e);
            }
        }
    }

    /**
     * @param array $ignore_params pass array containing the params to be ignored for caching.
     */
    private function parse()
    {
        $rm = Util::getRequestMethod();
        if (strcasecmp($rm, 'get') !== 0) {
            $this->ignoreRequest("request-method-$rm");
            return;
        }
        //process request
        if (isset($_SERVER['REQUEST_URI'])) {
            list($urlpart, $qspart) = array_pad(explode('?', $_SERVER['REQUEST_URI']), 2, '');
            parse_str($qspart, $qsvars);

            if (isset($qsvars['rl-no-optimization'])) {
                unset($qsvars['rl-no-optimization']);
                $this->isNoOptimization = true;
                $this->ignoreRequest("no-optimization");
            }

            if (isset($qsvars['rl-warmup'])) {
                unset($qsvars['rl-warmup']);
                $this->ignoreRead = true;
                $this->isWarmup = true;
            }

            if (isset($qsvars['rltest'])) {
                $this->ignoreRead = false;
                unset($qsvars['rltest']);
            }

            if (isset($qsvars['rl-rand'])) {
                unset($qsvars['rl-rand']);
            }

            if (isset($qsvars['rl-only-after'])) {
                $this->onlyAfter = ($qsvars['rl-only-after'] / 1000);
                unset($qsvars['rl-only-after']);
            }

            $newqs = http_build_query($qsvars);
            $_SERVER['REQUEST_URI'] = $urlpart . (empty($newqs) ?  '' : '?' . $newqs);
        }
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        $http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $raw_link = ($this->isHTTPS() ? "https" : "http") . "://$http_host$request_uri";

        $parsed_url = parse_url($raw_link);
        $query = empty($parsed_url['query']) ? '' : trim($parsed_url['query']);

        $this->requestURI = trim(@$parsed_url['path']) . (empty($query) ? '' : '?' . $query);;
        $host = trim(@$parsed_url['host']);
        $scheme = trim(@$parsed_url['scheme']);
        $this->requestURL  = $scheme . '://' . $host . $this->requestURI;
    }

    private function isHTTPS()
    {
        return (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) || (isset($_SERVER['HTTPS']) && strcmp($_SERVER['HTTPS'], "off") !== 0);
    }

    private function serve()
    {
        if (!$this->ignoreRead) {
            if ($this->cacheFile->serve()) {
                exit;
            } else {
                Util::sendHeader('x-rl-cache: miss', true);
            }
        } else if ($this->isWarmup) {
            if ($this->cacheFile->exists(Cache::TTL_LONG, $this->onlyAfter)) {
                Util::sendHeader('x-rl-cache: fresh', true);
                exit;
            } else {
                Util::sendHeader('x-rl-cache: stale', true);
            }
        } else {
            Util::sendHeader('x-rl-skip: ' . $this->ignoreReason, true);
        }

        if ($this->isNoOptimization || $this->isWarmup) {
            Util::sendHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0', true);
            Util::sendHeader('Cache-Control: post-check=0, pre-check=0', false);
            Util::sendHeader('Pragma: no-cache', true);
        }

        ob_start([$this, 'addFooter']);
    }

    /**
     * Saves buffer and append footer to all requests
     */
    public function addFooter($buffer)
    {
        $code = http_response_code();
        if ($code != 200) {
            $this->ignoreRequest('status-' . $code);
        }

        if ($buffer !== false) {
            try {
                $bom = pack('H*', 'EFBBBF');
                if ($bom !== false) {
                    $buffer = preg_replace("/^($bom)*/", '', $buffer);
                }
                $headersList = headers_list();
                $headers = [];
                $contentType = NULL;
                foreach ($headersList as $h) {
                    $p = explode(':', $h, 2);
                    $headers[trim($p[0])] = [trim($p[1])];
                    if (strcasecmp(trim($p[0]), 'content-type') === 0) {
                        $contentType = $p[1];
                    }
                }
                $isHtml = $contentType && stripos($contentType, 'text/html') !== false;
                $isAmp = preg_match("/<html.*?\s(amp|⚡)(\s|=|>)/", $buffer);
                if ($isHtml && !$isAmp) {
                    $this->appendFooter($buffer);
                    if ($this->isWarmup && !$this->ignoreWrite) {
                        $this->cacheFile->save(Cache::TTL_SHORT, $buffer, $headers);
                        $this->refresh($this->requestURL, true);
                    }
                } else {
                    if ($this->debug) {
                        Util::sendHeader("x-rl-page: $isHtml $isAmp", true);
                    }
                }
            } catch (\Throwable $e) {
                if ($this->debug) {
                    $buffer = $e->getMessage();
                }
                Exc:: catch($e);
            } catch (\Exception $e) {
                if ($this->debug) {
                    $buffer = $e->getMessage();
                }
                Exc:: catch($e);
            }
        }
        return $buffer;
    }

    private function appendFooter(&$buffer)
    {
        Util::append($buffer, '<script data-rlskip="1">const rlOriginalPageTime=new Date("' . date('c') . '");!function(e,a){var r="searchParams",i="append",l="getTime",n=e.rlPageData||{},t=n.rlCached;a.cookie="rlCached="+(t?"1":"0")+"; path=/;";let g=new Date,c="Y"==n.rlCacheRebuild,m=n.exp?new Date(n.exp):g,o=m.getFullYear()>1970&&m[l]()-g[l]()<0;(!t||c||o)&&setTimeout(function e(){var a=new URL(location.href);a[r][i]("rl-warmup","1"),a[r][i]("rl-rand",g.getTime()),a[r][i]("rl-only-after",("object"==typeof rlOriginalPageTime?rlOriginalPageTime:g).getTime()),fetch(a)},1e3)}(this,document);</script></body>');
    }

    public function process()
    {
        $this->serve();
        $this->cacheFile->collectGarbage(strtotime('-1 hour'));
    }

    private function refresh($url, $force)
    {
        $api = new API($this->licenseKey);
        $api->setDebug($this->debug);
        $response = $api->refresh($this->cacheFile, $url, $force);
        $this->cacheFile->collectGarbage(strtotime('-5 minutes'));
        if ($this->debug) {
            Util::sendHeader('x-rl-debug-refresh:' . json_encode($response), true);
        }
        if (!empty($response['saved']) && !empty($this->purgeCallback)) {
            call_user_func_array($this->purgeCallback, [$url]);
        }
        exit;
    }

    public function setVariant($variant)
    {
        $this->cacheFile->setVariant($variant);
    }

    public function isWarmUp()
    {
        return $this->isWarmup;
    }

    public function registerPurgeCallback($cb)
    {
        $this->purgeCallback = $cb;
    }
}
