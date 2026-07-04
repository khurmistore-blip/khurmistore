<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/env.php';

echo "1. env() function value: [" . env('BIGBUY_SANDBOX', 'DEFAULT_USED') . "]\n";
echo "2. getenv() direct: [" . getenv('BIGBUY_SANDBOX') . "]\n";

$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    $raw = file_get_contents($envPath);
    foreach (explode("\n", $raw) as $line) {
        if (stripos($line, 'SANDBOX') !== false) {
            echo "3. .env SANDBOX line: [" . trim($line) . "]\n";
        }
    }
} else {
    echo "3. .env NOT FOUND at $envPath\n";
}

$cfg = require __DIR__ . '/config.php';
echo "4. config final bigbuy_sandbox: " . var_export($cfg['bigbuy_sandbox'], true) . "\n";
echo "5. config bigbuy_api_key present: " . (!empty($cfg['bigbuy_api_key']) ? 'yes' : 'NO') . "\n";
