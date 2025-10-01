<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Ternary operator condition is always false\\.$#',
	'identifier' => 'ternary.alwaysFalse',
	'count' => 1,
	'path' => __DIR__ . '/mailscanner/detail.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant EXIM_QUEUE_IN not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/mailscanner/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant EXIM_QUEUE_OUT not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/mailscanner/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant SENDMAIL_QUEUE_IN not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/mailscanner/functions.php',
];
$ignoreErrors[] = [
	'message' => '#^Constant SENDMAIL_QUEUE_OUT not found\\.$#',
	'identifier' => 'constant.notFound',
	'count' => 1,
	'path' => __DIR__ . '/mailscanner/functions.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
