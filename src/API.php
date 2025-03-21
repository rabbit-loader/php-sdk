<?php


namespace RabbitLoader\SDK;

class API
{

    private $hostV1 = '';
    private $hostV2 = '';
    private $licenseKey = '';
    private $platform = [];
    private $debug = false;

    public function __construct($licenseKey, $platform)
    {
        $this->hostV1 = 'https://api-v1.rabbitloader.com/';
        $this->hostV2 = 'https://api-v2.rabbitloader.com/';
        if (!empty($_ENV['RL_PHP_SDK_HOST'])) {
            $this->hostV1 = $_ENV['RL_PHP_SDK_HOST'];
            $this->hostV2 = $_ENV['RL_PHP_SDK_HOST'];
        }
        $this->licenseKey = $licenseKey;
        $this->platform = $platform;
    }

    /**
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    public function refreshV1(Cache $cf, $url, $force)
    {
        if ($this->debug) {
            Util::sendHeader('x-rl-refresh: start', true);
        }
        $response = [
            'url' => $url
        ];
        $httpCode = 0;
        try {
            if (!$cf->exists(Cache::TTL_SHORT)) {
                $response['short_missing'] = true;
                return;
            }
            if ($cf->exists(Cache::TTL_LONG) && !$force) {
                $response['long_found'] = true;
                return;
            }
            $headers =  json_decode($cf->get(Cache::TTL_SHORT, 'h'), true);
            if (empty($headers)) {
                return;
            }
            $html = $cf->get(Cache::TTL_SHORT, 'c');
            if (empty($html)) {
                return;
            }
            $fields = [
                'url_b64' => base64_encode($url),
                'html' => $html,
                'headers' => $headers,
                'plugins' => []
            ];
            if (WordPress::isWp()) {
                $fields['plugins'] = WordPress::plugins();
            }
            $this->remoteV1('page/get_cache', $fields, $result, $httpCode);
            if (!empty($result['data']['html'])) {
                $response['saved'] = $cf->save(Cache::TTL_LONG, $result['data']['html'], $result['data']['headers']);
                $response['deleted'] = $cf->delete(Cache::TTL_SHORT);
            } else {
                $response = $result;
            }
        } catch (\Throwable $e) {
            Exc::catch($e);
        }
        if ($this->debug) {
            Util::sendHeader('x-rl-refresh: finish', true);
            Util::sendHeader('x-rl-httpCode: ' . $httpCode, true);
        }
        return $response;
    }

    private function remoteV1($endpoint, &$fields, &$result, &$httpCode)
    {
        $ignoreSSL = true;
        $url = $this->hostV1 . 'api/v1/' . $endpoint;
        $fields_string = http_build_query($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->licenseKey,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_ENCODING, ''); //Curl automatically sends the appropriate header based on the supported algorithms, and automatically decodes the response.

        if ($ignoreSSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        $httpCode = 0;
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        if (!empty($curlError)) {
            if ($this->debug) {
                echo $curlError;
            }
            return;
        }
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        $result = json_decode($response, true);
        if ($result === null && $this->debug) {
            echo "Failed to decode JSON $response";
        }
        return true;
    }

    public function heartbeat()
    {
        try {
            $data = $this->platform;
            $data += [
                'error_log' => Exc::getAndClean(),
                'cdn_loop' => empty($_SERVER['HTTP_CDN_LOOP']) ? '' : $_SERVER['HTTP_CDN_LOOP'],
                'server_addr' => empty($_SERVER['SERVER_ADDR']) ? '' : $_SERVER['SERVER_ADDR'],
            ];

            if (empty($data['cdn_loop']) && !empty($_SERVER['HTTP_INCAP_CLIENT_IP'])) {
                $data['cdn_loop'] = 'incap';
            }
            if (empty($data['server_addr']) && !empty($_SERVER['LOCAL_ADDR'])) {
                $data['server_addr'] = $_SERVER['LOCAL_ADDR'];
            }
            $this->remoteV1('domain/heartbeat', $data, $result, $httpCode);
            Util::sendHeader('x-rl-hb-code: ' . $httpCode, true);
        } catch (\Throwable $e) {
            Exc::catch($e);
            Util::sendHeader('x-rl-hb-thro: ' . $e->getMessage() . ':' . $e->getLine(), true);
        } catch (\Exception $e) {
            Exc::catch($e);
            Util::sendHeader('x-rl-hb-exc: ' . $e->getMessage() . ':' . $e->getLine(), true);
        }
    }


    public function refreshV2(Cache $cf, $url, $force)
    {
        if ($this->debug) {
            Util::sendHeader('x-rl-refresh: start', true);
        }
        $response = [
            'url' => $url
        ];
        $httpCode = 0;
        try {
            if (!$cf->exists(Cache::TTL_SHORT)) {
                $response['short_missing'] = true;
                return;
            }
            if ($cf->exists(Cache::TTL_LONG) && !$force) {
                $response['long_found'] = true;
                return;
            }
            $headers =  json_decode($cf->get(Cache::TTL_SHORT, 'h'), true);
            if (empty($headers)) {
                return;
            }
            $html = $cf->get(Cache::TTL_SHORT, 'c');
            if (empty($html)) {
                return;
            }
            $fields = [
                'url' => $url,
                'urlBase64' => base64_encode($url),
                'html' => $html,
                'headers' => $headers,
                'plugins' => []
            ];
            if (WordPress::isWp()) {
                $fields['plugins'] = WordPress::plugins();
            }
            $errorMessages = '';
            $this->remoteV2("url/domain/defaultdid/page/optimize", $fields, $result, $httpCode, $errorMessages);
            if (empty($result['error']) && !empty($result['html'])) {
                $response['saved'] = $cf->save(Cache::TTL_LONG, $result['html'], $result['headers']);
                $response['deleted'] = $cf->delete(Cache::TTL_SHORT);
            } else {
                $response = $result;
                Util::sendHeader('x-rl-ble: ' . $result['error'], true);
            }
            Util::sendHeader('x-rl-last-error: ' . $errorMessages, true);
        } catch (\Throwable $e) {
            Exc::catch($e);
        }
        if ($this->debug) {
            Util::sendHeader('x-rl-refresh: finish', true);
            Util::sendHeader('x-rl-httpCode: ' . $httpCode, true);
        }
        return $response;
    }

    private function remoteV2($endpoint, &$fields, &$result, &$httpCode, &$errMsg)
    {
        $ignoreSSL = true;
        $url = $this->hostV2 . $endpoint;
        $encodedDate = json_encode($fields);
        if (!$encodedDate) {
            $errMsg = json_last_error_msg();
            return;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedDate);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->licenseKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_ENCODING, ''); //Curl automatically sends the appropriate header based on the supported algorithms, and automatically decodes the response.

        if ($ignoreSSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        $httpCode = 0;
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        if (!empty($curlError)) {
            $errMsg = $curlError;
            return;
        }
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        curl_close($ch);
        $result = json_decode($response, true);
        if ($result === null) {
            $errMsg = json_last_error_msg();
        }
        return true;
    }
}
