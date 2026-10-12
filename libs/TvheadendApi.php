<?php

declare(strict_types=1);

//HTTP-Client für die JSON-API von Tvheadend. Die Zugangsdaten gehen im Authorization-Header mit,
//nie in der URL.
class TvheadendApi
{
    private string $baseUrl;
    private string $authHeader;
    private int $timeout;

    public function __construct(string $host, int $port, bool $ssl, string $username, string $password, int $timeout = 5)
    {
        $this->baseUrl = ($ssl ? 'https' : 'http') . '://' . $host . ':' . $port . '/';
        $this->authHeader = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
        $this->timeout = $timeout;
    }

    //Liefert immer ein Array: code (HTTP-Status, 0 bei Verbindungsfehler), data (decodierte JSON-Antwort
    //oder null) und error (Text, leer bei Erfolg).
    public function request(string $path, array $params = [], string $method = 'GET'): array
    {
        $url = $this->baseUrl . ltrim($path, '/');
        $query = http_build_query($this->prepareParams($params));
        $header = [$this->authHeader, 'Accept: application/json'];
        $options = [
            'method'        => $method,
            'timeout'       => $this->timeout,
            'ignore_errors' => true,
            'header'        => $header
        ];
        if ($method === 'POST') {
            $options['header'][] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = $query;
        } elseif ($query !== '') {
            $url .= '?' . $query;
        }

        $http_response_header = [];
        $body = @file_get_contents($url, false, stream_context_create([
            'http' => $options,
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
        ]));
        if ($body === false) {
            return ['code' => 0, 'data' => null, 'error' => 'Connection failed'];
        }

        $code = 0;
        if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $match)) {
            $code = (int) $match[1];
        }
        if ($code !== 200) {
            return ['code' => $code, 'data' => null, 'error' => 'HTTP ' . $code];
        }

        $data = json_decode($body, true);
        if ($data === null && trim($body) !== 'null') {
            //Aktionen ohne Rückgabe und /ping liefern keinen JSON-Inhalt.
            return ['code' => $code, 'data' => ['raw' => $body], 'error' => ''];
        }
        return ['code' => $code, 'data' => $data, 'error' => ''];
    }

    //Lädt eine Datei (zum Beispiel ein Senderlogo) mit Anmeldung. Liefert code, type (Content-Type) und body (binär).
    public function requestRaw(string $path): array
    {
        $http_response_header = [];
        $body = @file_get_contents($this->baseUrl . ltrim($path, '/'), false, stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => $this->timeout, 'ignore_errors' => true, 'header' => [$this->authHeader]],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
        ]));
        if ($body === false) {
            return ['code' => 0, 'type' => '', 'body' => ''];
        }
        $code = 0;
        $type = '';
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match)) {
                $code = (int) $match[1];
            } elseif (stripos($line, 'Content-Type:') === 0) {
                $type = trim(substr($line, 13));
            }
        }
        return ['code' => $code, 'type' => $type, 'body' => $body];
    }

    //Arrays und Objekte erwartet Tvheadend als JSON-Text, Booleans als 0/1.
    private function prepareParams(array $params): array
    {
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $params[$key] = $value ? '1' : '0';
            } elseif (is_array($value)) {
                $params[$key] = json_encode($value);
            }
        }
        return $params;
    }
}
