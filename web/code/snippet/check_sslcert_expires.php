<?php
// NYSL License (Version 0.9982 http://www.kmonos.net/nysl/)

// date_default_timezone_set('Asia/Tokyo');

$isCLI = php_sapi_name() === 'cli';

$hosts = [
  'lavoscore.org',
];

if ($isCLI) {
  if ($argc >= 2) {
    $hosts = [];
    for ($i = 1; $i < $argc; ++$i) {
      $hosts[] = $argv[$i];
    }
  }
}
else {
  if (isset($_GET['q'])) {
    $hosts = explode(',', $_GET['q']);
  }
}

$main = new CheckSSLCertExpires();
$result = $main->checkExpire($hosts);

CheckSSLCertExpires::renderJson($result, @$_GET['callback']);

class CheckSSLCertExpires {
  const CONNECTION_TIMEOUT = 5;
  protected $today = null;

  public function checkExpire($hosts) {
    $hosts = (array)$hosts;

    $result = [];
    $this->today = self::datetime(strtotime('now'));
    foreach ($hosts as $host) {
      $result[$host] = $this->getExpires($host);
    }

    return $result;
  }

  public static function isMatchSuffix($haystack, $needle) {
    return strpos(strrev($haystack), strrev($needle)) === 0;
  }

  public static function datetime($timestamp) {
    return date('Y/m/d H:i:s', $timestamp);
  }

  public static function jst2utc($datetimeStr) {
    $dt = new DateTime($datetimeStr);
    $dt->setTimeZone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d\TH:i:s\Z');
  }

  public static function diffdays($startDatetime, $endDatetime) {
    return (int)date_diff(new DateTime($startDatetime), new DateTime($endDatetime))->format('%r%a');
  }

  protected function getStreamResource($domainNameWithPort) {
    $contextOptions = ['ssl' => ['capture_peer_cert' => true]];
    $streamContext = stream_context_create($contextOptions);

    $remoteSocket = sprintf('ssl://%s', $domainNameWithPort);
    $streamResource = @stream_socket_client($remoteSocket, $errno, $errstr, self::CONNECTION_TIMEOUT, STREAM_CLIENT_CONNECT, $streamContext);

    return $streamResource;
  }

  protected function isContainedInSubjectAltName($subjectAltName, $domainName) {
    $str = str_replace(' ', '', $subjectAltName);
    $str = str_replace('DNS:', '', $str);
    $altNames = explode(',', $str);

    foreach ($altNames as $altName) {
      // FQDN
      if ($altName === $domainName) return true;

      // Wild-card
      if ($altName[0] === '*') {
        if (self::isMatchSuffix($domainName, substr($altName, 1))) return true;
      }
    }

    return false;
  }

  protected function getExpires($host) {
    preg_match('/^(?<domainName>.*?)(?::(?<port>\d+))?$/', $host, $m);
    $port = isset($m['port']) ? (int)$m['port'] : 443;
    $domainName = $m['domainName'];
    $domainNameWithPort = sprintf('%s:%s', $domainName, $port);

    $streamResource = $this->getStreamResource($domainNameWithPort);
    if ($streamResource === false) return null;

    $params = stream_context_get_params($streamResource);
    $parsed = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

    $result = [];

    $result['serial'] = $parsed['serialNumberHex'];

    $ocsp = self::getOCSP($domainName);
    $result['OCSP_serial'] = $ocsp['serial'];
    $result['OCSP_cert_status'] = $ocsp['cert_status'];
    $result['OCSP_this_update'] = $ocsp['this_update'];
    $result['OCSP_next_update'] = $ocsp['next_update'];

    $result['domainName'] = $domainName;
    $result['port'] = $port;

    $result['subjectAltName'] = null;
    if (isset($parsed['extensions']['subjectAltName'])) {
      $subjectAltName = $parsed['extensions']['subjectAltName'];
      $result['subjectAltName'] = $subjectAltName;
      $result['is_valid'] = $this->isContainedInSubjectAltName($subjectAltName, $domainName);
    };

    $result['CA'] = $parsed['issuer']['O'];

    $result['updated_at'] = self::datetime($parsed['validFrom_time_t']);
    $result['expires_at'] = self::datetime($parsed['validTo_time_t']);
    $result['today'] = $this->today;

    $result['UTC'] = [
      'updated_at' => self::jst2utc($result['updated_at']),
      'expires_at' => self::jst2utc($result['expires_at']),
      'today'=> self::jst2utc($result['today']),
    ];

    $result['remaining_days'] = self::diffdays($result['today'], $result['expires_at']);

    return $result;
  }

  public static function renderJson($object, $callbackName = null) {
    $json = json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (!preg_match('/\A[A-Za-z][0-9A-Za-z_]*\z/', $callbackName ?? '')) $callbackName = null;
    $useJsonp = !empty($callbackName);

    if ($useJsonp) {
      header('Content-Type: application/javascript; charset=utf-8');
      $jsonp = sprintf('%s(%s);', $callbackName, $json);
      echo $jsonp, PHP_EOL;
    }
    else {
      header('Content-Type: application/json; charset=utf-8');
      echo $json, PHP_EOL;
    }
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

  public static function getOCSP($domain) {
    $arg = sprintf('%s:https', $domain);
    $cmd = self::mycmd('openssl s_client -connect %s -servername %s -CApath /etc/ssl/certs -status < /dev/null 2> /dev/null | ag "Serial Number|Cert Status|This Update|Next Update" | perl -pe "s/^\s*[^:]+:\s*//"', $arg, $domain);
    self::myexec($cmd, $output);

    $ocsp = [
      'serial' => $output[0] ?? null,
      'cert_status' => $output[1] ?? null,
      'this_update' => $output[2] ?? null,
      'next_update' => $output[3] ?? null,
    ];

    return $ocsp;
  }
}
