<?php

/**
 * Запуск:
 * целевой URL указать в $base
 * php -S 127.0.0.1:9001 hackernews.php
 *
 * После этого в браузере можно открывать http://127.0.0.1:9001/
 * и все запросы пойдут через прокси на указанный в $base адрес.
 */
$base = 'https://news.ycombinator.com/';
try {
    $stderr = fopen('php://stderr', 'w');
    $url = str_ireplace('index.php', '', $_SERVER['REQUEST_URI']);
    $path = parse_url($url, PHP_URL_PATH);
    $newPath = ltrim($path, '/');
    if ($query = parse_url($url, PHP_URL_QUERY)) {
        $newPath .= '?' . $query;
    }
    $proxyUrl = $base . $newPath;
    $contents = @file_get_contents($proxyUrl);
    $headers = $http_response_header;
    $firstLine = $headers[0];
    if ($contents === false) {
        fwrite($stderr, "Request failed: $proxyUrl - $firstLine\n");
        header("HTTP/1.1 503 Proxy error");
        die("Proxy failed to get contents at $proxyUrl");
    }
    fwrite($stderr, "$proxyUrl - OK: $firstLine\n");
    $allowedHeaders = "!^(http/1.1|server:|content-type:|last-modified|access-control-allow-origin|Content-Length:|Accept-Ranges:|Date:|Via:|Connection:|X-|age|cache-control|vary)!i";
    foreach ($headers as $header) {
        if (preg_match($allowedHeaders, $header)) {
            fwrite($stderr, "+ $header\n");
            header($header);
        } else {
            fwrite($stderr, "- $header\n");
        }
    }
    $doc = new DOMDocument();
    @$doc->loadHTML($contents);
    $doTransformation = [
        'COUNT' => 6,
        'ADD_BEFORE' => '',
        'ADD_AFTER' => '™'
    ];
    if ($doTransformation && $doc) {
        $xpath = new DOMXpath($doc);
        $elements = $xpath->query("//*[string-length() = {$doTransformation['COUNT']}]");
        if (!is_null($elements)) {
            foreach ($elements as $element) {
                $element->textContent = preg_replace_callback(
                    "#\b(\w{{$doTransformation['COUNT']}})\b#im",
                    function ($val) use ($doTransformation) {
                        return "{$doTransformation['ADD_BEFORE']}{$val[0]}{$doTransformation['ADD_AFTER']}";
                    },
                    $element->textContent
                );
            }
        }
        $elements = $xpath->query("//a[contains(@href,'//')]");
        if (!is_null($elements)) {
            foreach ($elements as $element) {
                $element->setAttribute('onclick', 'return false');
            }
        }
    }
    echo $doc->saveHTML();
} catch (\Throwable $th) {
    echo "({$th->getCode()}) {$th->getMessage()}";
}
