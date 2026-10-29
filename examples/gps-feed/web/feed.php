<?php
/**
 * AJAX endpoint — called by index.php every 5 seconds.
 *
 * Reads the stored fromVersion from the PHP session, calls GetFeed for
 * LogRecord, saves the returned toVersion back to the session, and
 * returns the new records as JSON.
 */
require __DIR__ . '/../../../vendor/autoload.php';
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure' => !empty($_SERVER['HTTPS']),
    'use_strict_mode' => true,
]);
header('Content-Type: application/json');

if (!isset($_SESSION['credentials'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$creds = $_SESSION['credentials'];

try {
    if (!is_string($creds['server'] ?? null)
        || !preg_match('/^([a-z0-9-]+\.)*geotab\.com$/iD', $creds['server'])) {
        throw new \InvalidArgumentException('Invalid server');
    }
    // Re-use the existing session — no re-authentication needed.
    $api = new Geotab\API(
        $creds['username'],
        null,
        $creds['database'],
        $creds['server']
    );
    $api->getCredentials()->setSessionId($creds['sessionId']);

    $params = ['typeName' => 'LogRecord', 'resultsLimit' => 100];
    if (!empty($_SESSION['fromVersion'])) {
        $params['fromVersion'] = $_SESSION['fromVersion'];
    }

    $result = $api->call('GetFeed', $params);

    // Advance the bookmark for the next poll.
    if (!empty($result['toVersion'])) {
        $_SESSION['fromVersion'] = $result['toVersion'];
    }

    echo json_encode([
        'records'   => $result['data']      ?? [],
        'toVersion' => $result['toVersion'] ?? '',
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e instanceof Geotab\MyGeotabException
        ? $e->getMessage() : 'Unable to retrieve GPS data. Please try again.']);
}
