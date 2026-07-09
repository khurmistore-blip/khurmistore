<?php
if (!defined('CAPI_CONFIG_LOADED')) {
    $configPath = __DIR__ . '/capi-config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    }
}

class MetaCAPI
{
    const API_VERSION = 'v21.0';
    const TIMEOUT     = 8;

    public static function sendEvent($eventName, array $opts = [])
    {
        if (!defined('CAPI_PIXEL_ID') || !defined('CAPI_ACCESS_TOKEN')) {
            self::log('CONFIG MISSING');
            return ['ok' => false, 'http' => 0, 'body' => 'config missing', 'event_id' => ''];
        }

        $eventId   = isset($opts['event_id']) ? (string) $opts['event_id'] : self::randomEventId();
        $eventTime = isset($opts['event_time']) ? (int) $opts['event_time'] : time();

        $event = [
            'event_name'       => $eventName,
            'event_time'       => $eventTime,
            'event_id'         => $eventId,
            'action_source'    => 'website',
            'event_source_url' => isset($opts['event_source_url']) ? $opts['event_source_url'] : self::currentUrl(),
            'user_data'        => self::buildUserData($opts),
        ];

        $customData = self::buildCustomData($opts);
        if (!empty($customData)) {
            $event['custom_data'] = $customData;
        }

        $payload = ['data' => [$event]];

        if (defined('CAPI_TEST_EVENT_CODE') && CAPI_TEST_EVENT_CODE !== '') {
            $payload['test_event_code'] = CAPI_TEST_EVENT_CODE;
        }

        $url = 'https://graph.facebook.com/' . self::API_VERSION . '/'
             . CAPI_PIXEL_ID . '/events?access_token=' . urlencode(CAPI_ACCESS_TOKEN);

        $result = self::post($url, $payload);
        $result['event_id'] = $eventId;

        if (!$result['ok']) {
            self::log($eventName . ' FAILED [' . $result['http'] . '] ' . $result['body']);
        } else {
            self::log($eventName . ' OK event_id=' . $eventId);
        }

        return $result;
    }

    public static function purchase(array $opts)
    {
        if (!isset($opts['event_id']) && !empty($opts['order_id'])) {
            $opts['event_id'] = self::eventIdForOrder($opts['order_id']);
        }
        if (!isset($opts['currency'])) {
            $opts['currency'] = 'EUR';
        }
        return self::sendEvent('Purchase', $opts);
    }

    public static function eventIdForOrder($orderId)
    {
        return 'purchase_' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $orderId);
    }

    private static function buildUserData(array $opts)
    {
        $ud = [];

        if (!empty($opts['email'])) {
            $ud['em'] = [self::hash(strtolower(trim($opts['email'])))];
        }
        if (!empty($opts['phone'])) {
            $phone = self::normalizePhone($opts['phone']);
            if ($phone !== '') {
                $ud['ph'] = [self::hash($phone)];
            }
        }
        if (!empty($opts['first_name'])) {
            $ud['fn'] = [self::hash(self::normalizeName($opts['first_name']))];
        }
        if (!empty($opts['last_name'])) {
            $ud['ln'] = [self::hash(self::normalizeName($opts['last_name']))];
        }
        if (!empty($opts['city'])) {
            $ud['ct'] = [self::hash(self::normalizeName($opts['city']))];
        }
        if (!empty($opts['state'])) {
            $ud['st'] = [self::hash(self::normalizeName($opts['state']))];
        }
        if (!empty($opts['zip'])) {
            $ud['zp'] = [self::hash(preg_replace('/\s+/', '', strtolower(trim($opts['zip']))))];
        }
        if (!empty($opts['country'])) {
            $ud['country'] = [self::hash(strtolower(trim($opts['country'])))];
        }

        $ip = isset($opts['client_ip']) ? $opts['client_ip'] : self::clientIp();
        if ($ip !== '') {
            $ud['client_ip_address'] = $ip;
        }

        $ua = isset($opts['client_user_agent'])
            ? $opts['client_user_agent']
            : (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
        if ($ua !== '') {
            $ud['client_user_agent'] = $ua;
        }

        $fbp = isset($opts['fbp']) ? $opts['fbp'] : self::cookie('_fbp');
        if ($fbp !== '') {
            $ud['fbp'] = $fbp;
        }

        $fbc = isset($opts['fbc']) ? $opts['fbc'] : self::resolveFbc();
        if ($fbc !== '') {
            $ud['fbc'] = $fbc;
        }

        return $ud;
    }

    private static function buildCustomData(array $opts)
    {
        $cd = [];

        if (isset($opts['value'])) {
            $cd['value'] = round((float) $opts['value'], 2);
        }
        if (!empty($opts['currency'])) {
            $cd['currency'] = strtoupper($opts['currency']);
        }
        if (!empty($opts['order_id'])) {
            $cd['order_id'] = (string) $opts['order_id'];
        }

        if (!empty($opts['contents']) && is_array($opts['contents'])) {
            $contents = [];
            $ids      = [];
            foreach ($opts['contents'] as $item) {
                if (empty($item['id'])) {
                    continue;
                }
                $row = ['id' => (string) $item['id']];
                $row['quantity'] = isset($item['quantity']) ? (int) $item['quantity'] : 1;
                if (isset($item['item_price'])) {
                    $row['item_price'] = round((float) $item['item_price'], 2);
                }
                $contents[] = $row;
                $ids[]      = (string) $item['id'];
            }
            if (!empty($contents)) {
                $cd['contents']     = $contents;
                $cd['content_ids']  = $ids;
                $cd['content_type'] = 'product';
                $cd['num_items']    = count($contents);
            }
        }

        return $cd;
    }

    private static function hash($value)
    {
        return hash('sha256', $value);
    }

    private static function normalizeName($name)
    {
        return preg_replace('/\s+/', '', strtolower(trim($name)));
    }

    private static function normalizePhone($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) === 9 && preg_match('/^[679]/', $digits)) {
            $digits = '34' . $digits;
        }
        if (strpos($digits, '0034') === 0) {
            $digits = substr($digits, 2);
        }
        return $digits;
    }

    private static function cookie($name)
    {
        return isset($_COOKIE[$name]) ? trim($_COOKIE[$name]) : '';
    }

    private static function resolveFbc()
    {
        $fbc = self::cookie('_fbc');
        if ($fbc !== '') {
            return $fbc;
        }
        if (!empty($_GET['fbclid'])) {
            return 'fb.1.' . (time() * 1000) . '.' . $_GET['fbclid'];
        }
        return '';
    }

    private static function clientIp()
    {
        $candidates = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($candidates as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }
            $value = $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                $value = trim($parts[0]);
            }
            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }
        }
        return '';
    }

    private static function currentUrl()
    {
        if (empty($_SERVER['HTTP_HOST'])) {
            return 'https://khurmistore.es/';
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $uri    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . $uri;
    }

    private static function randomEventId()
    {
        return bin2hex(random_bytes(16));
    }

    private static function post($url, array $payload)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);

        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'http' => 0, 'body' => 'cURL error: ' . $err];
        }

        return ['ok' => ($http >= 200 && $http < 300), 'http' => $http, 'body' => $body];
    }

    private static function log($message)
    {
        if (!defined('CAPI_LOG_FILE') || CAPI_LOG_FILE === '') {
            return;
        }
        @file_put_contents(CAPI_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
