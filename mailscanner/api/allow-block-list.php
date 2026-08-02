<?php

const MAILWATCH_ALLOW_BLOCK_LIST_CONTRACT = 'mailwatch.allow-block-list.v1';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-cache');
header('X-MailWatch-Contract: ' . MAILWATCH_ALLOW_BLOCK_LIST_CONTRACT);

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

/**
 * @param mysqli $database
 *
 * @return list<array{to_address: string, from_address: string}>
 */
function loadListSnapshot($database, string $table): array
{
    $query = "SELECT to_address, from_address FROM {$table}
              UNION ALL
              SELECT user_filters.filter AS to_address, {$table}.from_address
              FROM {$table}
              INNER JOIN user_filters ON {$table}.to_address = user_filters.username";
    $result = $database->query($query);
    if (false === $result) {
        throw new RuntimeException('Unable to read list snapshot');
    }

    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = [
            'to_address' => strtolower((string)($row['to_address'] ?? '')),
            'from_address' => strtolower((string)($row['from_address'] ?? '')),
        ];
    }
    $result->free();

    usort(
        $entries,
        static fn(array $left, array $right): int => [$left['to_address'], $left['from_address']]
            <=> [$right['to_address'], $right['from_address']]
    );

    return $entries;
}

try {
    $database = Database::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $allowlist = loadListSnapshot($database, 'allowlist');
    $blocklist = loadListSnapshot($database, 'blocklist');
    $database->close();
} catch (Throwable) {
    MailWatchApi::error(500, 'snapshot_unavailable', 'Snapshot unavailable');
}

$snapshotData = [
    'allowlist' => $allowlist,
    'blocklist' => $blocklist,
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
    'contract' => MAILWATCH_ALLOW_BLOCK_LIST_CONTRACT,
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
