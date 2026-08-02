<?php

// all response are JSON encoded
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../conf.php';

if (!defined('API_KEY')) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized - Set an API KEY to use this API'], JSON_THROW_ON_ERROR);
    exit;
}

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/MailLogEntry.php';

function isValidApiKey(?string $apiKey): bool
{
    if (null === $apiKey) {
        return false;
    }

    if (!defined('API_KEY')) {
        return false;
    }

    return hash_equals(API_KEY, $apiKey);
}

function getApiKeyToken(): ?string
{
    if (isset($_SERVER['HTTP_X_MAILWATCH_API_KEY'])) {
        return $_SERVER['HTTP_X_MAILWATCH_API_KEY'];
    }

    return null;
}

function getIdempotencyKey(): ?string
{
    if (isset($_SERVER['HTTP_IDEMPOTENCY_KEY'])) {
        return strtolower(trim($_SERVER['HTTP_IDEMPOTENCY_KEY']));
    }

    return null;
}

// Check if is POST request
if ('POST' !== $_SERVER['REQUEST_METHOD']) {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Verify API key
$apiKeyToken = getApiKeyToken();
if (null === $apiKeyToken || !isValidApiKey($apiKeyToken)) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get JSON payload
$maxPayloadBytes = defined('API_MAX_PAYLOAD_BYTES')
    ? (int)API_MAX_PAYLOAD_BYTES
    : 10 * 1024 * 1024;
if (isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > $maxPayloadBytes) {
    http_response_code(413); // Payload Too Large
    echo json_encode(['error' => 'Payload Too Large']);
    exit;
}
$json = file_get_contents('php://input', false, null, 0, $maxPayloadBytes + 1);
if (false === $json || strlen($json) > $maxPayloadBytes) {
    http_response_code(413); // Payload Too Large
    echo json_encode(['error' => 'Payload Too Large']);
    exit;
}
$data = json_decode($json, true);

if (JSON_ERROR_NONE !== json_last_error()) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}
if (!is_array($data) || array_is_list($data)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid data']);
    exit;
}
$legacyFields = [
    'spamwhitelisted' => 'spamallowlisted',
    'spamblacklisted' => 'spamblocklisted',
    'mcpwhitelisted' => 'mcpallowlisted',
    'mcpblacklisted' => 'mcpblocklisted',
];

foreach ($legacyFields as $legacyField => $currentField) {
    if (array_key_exists($legacyField, $data) && !array_key_exists($currentField, $data)) {
        $data[$currentField] = $data[$legacyField];
    }
}

try {
    $mailLogEntry = new MailLogEntry($data);
} catch (InvalidArgumentException) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid data']);
    exit;
}
if (!$mailLogEntry->isValid()) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid data']);
    exit;
}
foreach ($mailLogEntry->observations() as $observation) {
    error_log('MailWatch logmail compatibility observation: ' . $observation);
}

$idempotencyKey = getIdempotencyKey();
if (null !== $idempotencyKey) {
    $expectedIdempotencyKey = hash(
        'sha256',
        $mailLogEntry->hostname . "\0" . $mailLogEntry->id . "\0" . $mailLogEntry->token
    );
    if (
        1 !== preg_match('/^[a-f0-9]{64}$/', $idempotencyKey)
        || '' === $mailLogEntry->hostname
        || '' === $mailLogEntry->token
        || !hash_equals($expectedIdempotencyKey, $idempotencyKey)
    ) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Invalid idempotency key']);
        exit;
    }
}

// Prepare insert query
$dbLink = Database::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

$query = 'INSERT INTO maillog (timestamp, id, size, from_address, from_domain, to_address, to_domain, subject, clientip, archive, isspam, ishighspam, issaspam, isrblspam, spamallowlisted, spamblocklisted, sascore, spamreport, virusinfected, nameinfected, otherinfected, report, ismcp, ishighmcp, issamcp, mcpallowlisted, mcpblocklisted, mcpsascore, mcpreport, hostname, date, time, headers, quarantined, rblspamreport, token, messageid, ingestion_id)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

$stmt = $dbLink->prepare($query);
if (!$stmt) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to prepare statement']);
    exit;
}
$stmt->bind_param(
    'ssisssssssiiiiiidsiiisiiiiidsssssissss',
    $mailLogEntry->timestamp,
    $mailLogEntry->id,
    $mailLogEntry->size,
    $mailLogEntry->from,
    $mailLogEntry->from_domain,
    $mailLogEntry->to,
    $mailLogEntry->to_domain,
    $mailLogEntry->subject,
    $mailLogEntry->clientip,
    $mailLogEntry->archiveplaces,
    $mailLogEntry->isspam,
    $mailLogEntry->ishigh,
    $mailLogEntry->issaspam,
    $mailLogEntry->isrblspam,
    $mailLogEntry->spamallowlisted,
    $mailLogEntry->spamblocklisted,
    $mailLogEntry->sascore,
    $mailLogEntry->spamreport,
    $mailLogEntry->virusinfected,
    $mailLogEntry->nameinfected,
    $mailLogEntry->otherinfected,
    $mailLogEntry->reports,
    $mailLogEntry->ismcp,
    $mailLogEntry->ishighmcp,
    $mailLogEntry->issamcp,
    $mailLogEntry->mcpallowlisted,
    $mailLogEntry->mcpblocklisted,
    $mailLogEntry->mcpsascore,
    $mailLogEntry->mcpreport,
    $mailLogEntry->hostname,
    $mailLogEntry->date,
    $mailLogEntry->time,
    $mailLogEntry->headers,
    $mailLogEntry->quarantined,
    $mailLogEntry->rblspamreport,
    $mailLogEntry->token,
    $mailLogEntry->messageid,
    $idempotencyKey
);

try {
    if ($stmt->execute()) {
        http_response_code(201); // Created
        echo json_encode(['success' => 'Data inserted successfully']);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to insert data']);
    }
} catch (mysqli_sql_exception $exception) {
    if (1062 === $exception->getCode() && null !== $idempotencyKey) {
        http_response_code(200); // Existing request already completed
        echo json_encode(['success' => 'Data already inserted', 'duplicate' => true]);
    } else {
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to insert data']);
    }
} catch (Throwable) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Failed to insert data']);
}

$stmt->close();
$dbLink->close();
