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

OCSPStapling::requestOCSPStapling($query);

class OCSPStapling {
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

  public static function apiGetOCSP($domain) {
    $grep = self::isJsonResponse() ? ' | ag "^(?:\s{6}Serial Number|\s{4}Cert Status|\s{4}This Update|\s{4}Next Update)" | perl -pe "s/^\s*[^:]+:\s*//"' : '';
    $connect = sprintf('%s:https', $domain);
    $format = sprintf('openssl s_client -connect %%s -servername %%s -CApath /etc/ssl/certs -status < /dev/null 2> /dev/null%s', $grep);
    $cmd = self::mycmd($format, $connect, $domain);
    self::myexec($cmd, $output);
    return $output;
  }

  public static function requestOCSPStapling($query) {
    $result = self::apiGetOCSP($query);

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
        echo "Any Domain Names: OCSP Stapling checker\n\n";
        echo "Usage:\n";
        echo "  https://ssl.lavoscore.org/api/sslcert-expires/ocsp/?q=\n";
        echo "  https://ssl.lavoscore.org/api/sslcert-expires/ocsp/?type=json&q=\n";
        echo "\n";
        echo "  Domain Name: ?q=lavoscore.org\n";
      }
      else {
        echo "Failed. It appears to be an invalid value. Require \"q\" parameter DomainName.\n";
        var_dump($query);
      }
    }
    else {
      echo implode(PHP_EOL, $result), PHP_EOL;
    }
  }
}
