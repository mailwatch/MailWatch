<?php

declare(strict_types=1);

if ('cli-server' === PHP_SAPI) {
    $_mailWatchStaticRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_string($_mailWatchStaticRequestPath)) {
        $_mailWatchStaticFile = realpath(__DIR__ . $_mailWatchStaticRequestPath);
        $_mailWatchPublicRoot = realpath(__DIR__);
        $_mailWatchStaticExtension = strtolower(pathinfo($_mailWatchStaticRequestPath, PATHINFO_EXTENSION));

        if (
            false !== $_mailWatchStaticFile
            && false !== $_mailWatchPublicRoot
            && 'php' !== $_mailWatchStaticExtension
            && str_starts_with($_mailWatchStaticFile, $_mailWatchPublicRoot . DIRECTORY_SEPARATOR)
            && is_file($_mailWatchStaticFile)
            && 1 !== preg_match('~(?:^|/)\.~', $_mailWatchStaticRequestPath)
        ) {
            return false;
        }
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$_mailWatchRequestUri = $_SERVER['REQUEST_URI'] ?? '/';
$_mailWatchRequestPath = parse_url($_mailWatchRequestUri, PHP_URL_PATH);
if (!is_string($_mailWatchRequestPath)) {
    $_mailWatchRequestPath = '/';
}

$_mailWatchScriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$_mailWatchBasePath = str_ends_with($_mailWatchScriptName, '/index.php')
    ? rtrim(str_replace('\\', '/', dirname($_mailWatchScriptName)), '/.')
    : '';
if ('' !== $_mailWatchBasePath && str_starts_with($_mailWatchRequestPath, $_mailWatchBasePath . '/')) {
    $_mailWatchRequestPath = substr($_mailWatchRequestPath, strlen($_mailWatchBasePath));
}

$_mailWatchApplicationRoot = dirname(__DIR__);
$_mailWatchApiRoutes = [
    '/api/messages' => $_mailWatchApplicationRoot . '/mailscanner/api/logmail.php',
    '/api/allow-block-list' => $_mailWatchApplicationRoot . '/mailscanner/api/allow-block-list.php',
    '/api/spam-settings' => $_mailWatchApplicationRoot . '/mailscanner/api/spam-settings.php',
];

$_mailWatchHandler = $_mailWatchApiRoutes[$_mailWatchRequestPath] ?? null;
if (null !== $_mailWatchHandler) {
    require $_mailWatchHandler;
    exit;
}

$_mailWatchPage = (new \MailWatch\Routing\PageRouteRegistry())->pageForPath($_mailWatchRequestPath);
if (null !== $_mailWatchPage) {
    $_mailWatchHandler = $_mailWatchApplicationRoot . '/mailscanner/' . $_mailWatchPage;
    $_mailWatchPublicPath = '/' === $_mailWatchRequestPath ? '/index.php' : $_mailWatchRequestPath;
    $_SERVER['PHP_SELF'] = $_mailWatchBasePath . $_mailWatchPublicPath;
    $_SERVER['SCRIPT_NAME'] = $_mailWatchBasePath . $_mailWatchPublicPath;
    $_SERVER['SCRIPT_FILENAME'] = $_mailWatchHandler;

    if (!chdir(dirname($_mailWatchHandler))) {
        throw new \RuntimeException('Unable to enter the application directory');
    }

    require $_mailWatchHandler;
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
http_response_code(404);
echo json_encode([
    'contract' => 'mailwatch.api.error.v1',
    'error' => [
        'code' => 'route_not_found',
        'message' => 'Not Found',
    ],
], JSON_THROW_ON_ERROR);
