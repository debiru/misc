<?php

date_default_timezone_set('Asia/Tokyo');

$isCLI = php_sapi_name() === 'cli';

$hosts = [
  'example.com',
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

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;

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

  static public function isMatchSuffix($haystack, $needle) {
    return strpos(strrev($haystack), strrev($needle)) === 0;
  }

  static public function datetime($timestamp) {
    return date('Y/m/d H:i:s', $timestamp);
  }

  static public function diffdays($startDatetime, $endDatetime) {
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
    $result['remaining_days'] = self::diffdays($result['today'], $result['expires_at']);

    return $result;
  }
}
