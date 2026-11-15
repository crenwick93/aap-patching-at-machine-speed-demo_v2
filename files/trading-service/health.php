<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');
$payload = [
  'status' => 'UP',
  'service' => 'Trading Service',
  'host' => gethostname() ?: 'unknown',
  'php_version' => PHP_VERSION,
  'timestamp' => gmdate('c'),
];
echo json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n";
