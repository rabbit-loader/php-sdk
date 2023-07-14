<?php


namespace RabbitLoader\SDK;

class API
{

    private string $host = '';
    private string $licenseKey = '';
    private bool $debug = false;

    public function __construct($licenseKey)
    {
        $this->host = 'https://rabbitloader.com/';
        if (!empty($_ENV['RL_PHP_SDK_HOST'])) {
            $this->host = $_ENV['RL_PHP_SDK_HOST'];
        }
        $this->licenseKey = $licenseKey;
    }

    /**
     * @param bool $debug
     */
    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    public function refresh(Cache $cf, $url, $force)
    {
        if ($this->debug) {
            Util::sendHeader('x-rl-refresh: start', true);
        }
        $response = [
            'url'=>$url
        ];
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
            $this->remote('page/get_cache', [
                'url_b64' => base64_encode($url),
                'html' => $html,
                'headers' => $headers
            ], $result, $httpCode);
            if (!empty($result['data']['html'])) {
                $response['saved'] = $cf->save(Cache::TTL_LONG, $result['data']['html'], $result['data']['headers']);
                $response['deleted'] = $cf->delete(Cache::TTL_SHORT);
            } else {
                $response['error'] = $result;
            }
        } catch (\Throwable $e) {
            Exc:: catch($e);
        }
        if ($this->debug) {
            Util::sendHeader('x-rl-refresh: finish', true);
        }
        return $response;
    }

    private function remote($endpoint, $fields, &$result, &$httpCode)
    {
        $ignoreSSL = true;
        $url = $this->host . 'api/v1/' . $endpoint;
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
        $result = json_decode($response, true);
        if ($result === null && $this->debug) {
            echo "Failed to decode JSON $response";
        }
        return true;
    }
}
