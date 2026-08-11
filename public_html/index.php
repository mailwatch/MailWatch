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

// MailWatch is served from the root of its own host or virtual host, with the
// document root set to public_html. Paths it emits are absolute for that root.

$_mailWatchApplicationRoot = dirname(__DIR__);
$_mailWatchRoute = (new \MailWatch\Shared\Http\RouteRegistry())->match($_mailWatchRequestPath);

if (null !== $_mailWatchRoute && \MailWatch\Shared\Http\RouteType::Redirect === $_mailWatchRoute->type) {
    \MailWatch\Shared\Http\Response::movedPermanently($_mailWatchRoute->handler)->send();
    exit;
}

if (null !== $_mailWatchRoute && \MailWatch\Shared\Http\RouteType::Api === $_mailWatchRoute->type) {
    require_once $_mailWatchApplicationRoot . '/mailscanner/conf.php';

    if (!defined('API_KEY') || !is_string(API_KEY) || '' === API_KEY) {
        if ('messages' === $_mailWatchRoute->handler) {
            $_mailWatchMessage = defined('API_KEY') ? 'Unauthorized' : 'Unauthorized - Set an API KEY to use this API';
            \MailWatch\Shared\Http\JsonResponse::send(401, ['error' => $_mailWatchMessage]);
        }

        \MailWatch\Shared\Http\JsonResponse::error(401, 'unauthorized', 'Unauthorized');
    }

    try {
        $_mailWatchApplicationFactory = \MailWatch\ApplicationFactory::create();
        $_mailWatchController = match ($_mailWatchRoute->handler) {
            'messages' => $_mailWatchApplicationFactory->messageController(),
            'allow-block-list' => $_mailWatchApplicationFactory->allowBlockListController(),
            'spam-settings' => $_mailWatchApplicationFactory->spamSettingsController(),
            default => throw new \LogicException('Unrouted API handler: ' . $_mailWatchRoute->handler),
        };
    } catch (\MailWatch\Shared\Infrastructure\Configuration\InvalidConfiguration) {
        if ('messages' === $_mailWatchRoute->handler) {
            \MailWatch\Shared\Http\JsonResponse::send(500, ['error' => 'Invalid API configuration']);
        }

        \MailWatch\Shared\Http\JsonResponse::error(500, 'invalid_configuration', 'Invalid API configuration');
    }

    $_mailWatchController->handle();
}

if (null !== $_mailWatchRoute && \MailWatch\Shared\Http\RouteType::Controller === $_mailWatchRoute->type) {
    // A controller route still needs the environment the page scripts build:
    // configuration, translations and the session check. That bootstrap moves
    // out of mailscanner/ as the remaining pages are extracted.
    if (!chdir($_mailWatchApplicationRoot . '/mailscanner')) {
        throw new \RuntimeException('Unable to enter the application directory');
    }

    $_SERVER['PHP_SELF'] = $_mailWatchRequestPath;
    $_SERVER['SCRIPT_NAME'] = $_mailWatchRequestPath;

    require_once $_mailWatchApplicationRoot . '/mailscanner/functions.php';
    require $_mailWatchApplicationRoot . '/mailscanner/login.function.php';

    $_mailWatchResponse = match ($_mailWatchRoute->handler) {
        'antivirus-status' => \MailWatch\ApplicationFactory::antivirusStatusController(
            (string)$_mailWatchRoute->parameter('scanner')
        )->handle(
            \MailWatch\ApplicationFactory::antivirusScanner((string)$_mailWatchRoute->parameter('scanner'))
        ),
        default => throw new \LogicException('Unrouted controller handler: ' . $_mailWatchRoute->handler),
    };

    $_mailWatchResponse->send();
    dbclose();

    exit;
}

if (null !== $_mailWatchRoute && \MailWatch\Shared\Http\RouteType::Page === $_mailWatchRoute->type) {
    $_mailWatchPage = $_mailWatchRoute->handler;
    $_mailWatchHandler = $_mailWatchApplicationRoot . '/mailscanner/' . $_mailWatchPage;
    $_mailWatchPublicPath = '/' === $_mailWatchRequestPath ? '/index.php' : $_mailWatchRequestPath;
    $_SERVER['PHP_SELF'] = $_mailWatchPublicPath;
    $_SERVER['SCRIPT_NAME'] = $_mailWatchPublicPath;
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
