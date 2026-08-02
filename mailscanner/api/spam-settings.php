<?php

const MAILWATCH_SPAM_SETTINGS_CONTRACT = 'mailwatch.spam-settings.v1';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-cache');
header('X-MailWatch-Contract: ' . MAILWATCH_SPAM_SETTINGS_CONTRACT);

require_once __DIR__ . '/../conf.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/MailWatchApi.php';

if ('GET' !== ($_SERVER['REQUEST_METHOD'] ?? null)) {
    header('Allow: GET');
    MailWatchApi::error(405, 'method_not_allowed', 'Method Not Allowed');
}
if (!MailWatchApi::isAuthorized()) {
    MailWatchApi::error(401, 'unauthorized', 'Unauthorized');
}

$requestedVersion = $_SERVER['HTTP_X_MAILWATCH_CONTRACT_VERSION'] ?? '1';
if ('1' !== $requestedVersion) {
    MailWatchApi::error(406, 'unsupported_contract_version', 'Unsupported contract version');
}

try {
    $database = Database::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $result = $database->query(
        'SELECT username, spamscore, highspamscore, noscan
         FROM users
         WHERE spamscore > 0 OR highspamscore > 0 OR noscan > 0'
    );
    if (false === $result) {
        throw new RuntimeException('Unable to read spam settings snapshot');
    }

    $spamScores = [];
    $highSpamScores = [];
    $noScan = [];
    while ($row = $result->fetch_assoc()) {
        $username = strtolower((string)($row['username'] ?? ''));
        $spamScore = (float)($row['spamscore'] ?? 0);
        $highSpamScore = (float)($row['highspamscore'] ?? 0);
        $noScanValue = (int)($row['noscan'] ?? 0);

        if ($spamScore > 0) {
            $spamScores[$username] = $spamScore;
        }
        if ($highSpamScore > 0) {
            $highSpamScores[$username] = $highSpamScore;
        }
        if ($noScanValue > 0) {
            $noScan[] = $username;
        }
    }
    $result->free();
    $database->close();
} catch (Throwable) {
    MailWatchApi::error(500, 'snapshot_unavailable', 'Snapshot unavailable');
}

ksort($spamScores, SORT_STRING);
ksort($highSpamScores, SORT_STRING);
sort($noScan, SORT_STRING);
$snapshotData = [
    'spam_scores' => (object)$spamScores,
    'high_spam_scores' => (object)$highSpamScores,
    'no_scan' => $noScan,
];
$snapshotJson = json_encode($snapshotData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$snapshotVersion = hash('sha256', $snapshotJson);
$etag = '"' . $snapshotVersion . '"';
header('ETag: ' . $etag);

$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
if (is_string($ifNoneMatch)) {
    $validators = array_map('trim', explode(',', $ifNoneMatch));
    if (in_array('*', $validators, true) || in_array($etag, $validators, true)) {
        http_response_code(304);
        exit;
    }
}

$response = json_encode([
    'contract' => MAILWATCH_SPAM_SETTINGS_CONTRACT,
    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
    'snapshot_version' => $snapshotVersion,
    ...$snapshotData,
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
$maxResponseBytes = defined('API_MAX_SNAPSHOT_BYTES') ? (int)API_MAX_SNAPSHOT_BYTES : 5 * 1024 * 1024;
if (strlen($response) > $maxResponseBytes) {
    MailWatchApi::error(500, 'snapshot_too_large', 'Snapshot exceeds the configured response limit');
}

http_response_code(200);
echo $response;
