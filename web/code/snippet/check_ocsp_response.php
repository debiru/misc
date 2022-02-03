<?php

// NYSL License (Version 0.9982 http://www.kmonos.net/nysl/)

$isCLI = php_sapi_name() === 'cli';

$query = null;

if ($isCLI) {
  if ($argc >= 2) {
    $query = $argv[1];
  }
}
else {
  if (isset($_GET['q'])) {
    $query = explode(',', $_GET['q'])[0];
  }
}

OCSP::requestOCSP($query);

class OCSP {
  const CA_CERT = '/var/www/hosts/web/ssl/chain.pem';
  const RESPONDER = 'r3.o.lencr.org';

  public static function isJsonResponse() {
    return @$_GET['type'] === 'json';
  }

  public static function mycmd() {
    $arg_list = func_get_args();
    $format = array_shift($arg_list);
    foreach ($arg_list as &$arg) {
      $arg = escapeshellarg($arg);
    }
    return vsprintf($format, $arg_list);
  }

  public static function myexec($command, &$output = null, &$return_var = null) {
    exec($command, $output, $return_var);
    return $return_var === 0;
  }

  public static function apiGetOCSP($serial) {
    $url = sprintf('http://%s', self::RESPONDER);
    $host = sprintf('HOST=%s', self::RESPONDER);
    $grep = self::isJsonResponse() ? ' | ag "^(?:\s{6}Serial Number|\s{4}Cert Status|\s{4}This Update|\s{4}Next Update)" | perl -pe "s/^\s*[^:]+:\s*//"' : '';
    $format = sprintf("openssl ocsp -noverify -no_nonce -issuer %%s -serial %%s -url %%s -header %%s -text%s", $grep);
    $cmd = self::mycmd($format, self::CA_CERT, $serial, $url, $host);
    self::myexec($cmd, $output);
    return $output;
  }

  public static function apiGetSerial($domain) {
    $apiUrl = sprintf('https://ssl.lavoscore.org/api/sslcert-expires/?q=%s', rawurlencode($domain));
    $json = file_get_contents($apiUrl);
    $objMap = json_decode($json, true, 512, JSON_BIGINT_AS_STRING);
    $obj = $objMap[$domain];
    if (empty($obj)) return null;
    return $obj['serial'];
  }

  public static function getSerial($query) {
    $query = strtolower($query);
    preg_match('/\A(0x)?[0-9a-f]{36}\z/', $query, $m);
    $serial = !isset($m[0]) ? self::apiGetSerial($query) : $query;
    if (empty($serial)) return null;
    if (strpos($serial, '0x') !== 0) $serial = '0x' . $serial;
    return $serial;
  }

  public static function requestOCSP($query) {
    $serial = self::getSerial($query);

    $result = empty($serial) ? null : self::apiGetOCSP($serial);

    if (self::isJsonResponse()) {
      self::outputJson($result, $query);
    }
    else {
      self::outputText($result, $query);
    }
  }

  public static function outputJson($result, $query) {
    header('Content-Type: application/json; charset=utf-8');

    $ocsp = [
      'serial' => $result[0] ?? null,
      'cert_status' => $result[1] ?? null,
      'this_update' => $result[2] ?? null,
      'next_update' => $result[3] ?? null,
    ];

    $json = json_encode($ocsp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    echo $json, PHP_EOL;
  }

  public static function outputText($result, $query) {
    header('Content-Type: text/plain; charset=utf-8');

    if (empty($result)) {
      if (empty($query)) {
        echo "Let's Encrypt: OCSP checker\n\n";
        echo "Usage:\n";
        echo "  https://ssl.lavoscore.org/api/sslcert-expires/ocsp/?q=\n";
        echo "  https://ssl.lavoscore.org/api/sslcert-expires/ocsp/?type=json&q=\n";
        echo "\n";
        echo "       Serial: ?q=04460bAE02A944E3D3FE00E759F4C8FFF007\n";
        echo "   Serial Hex: ?q=0x04460bAE02A944E3D3FE00E759F4C8FFF007\n";
        echo "  Domain Name: ?q=lavoscore.org\n";
      }
      else {
        echo "Failed. It appears to be an invalid value. Require \"q\" parameter as Serial or DomainName.\n";
        echo "Serial is only allowed from Let's encrypt (it's length is 36).\n\n";
        var_dump($query);
      }
    }
    else {
      echo implode(PHP_EOL, $result), PHP_EOL;
    }
  }
}
