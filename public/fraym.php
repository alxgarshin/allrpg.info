<?php

declare(strict_types=1);

/*
 * This file is part of the Fraym package.
 *
 * (c) Alex Garshin <alxgarshin@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

foreach ([__DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

use Fraym\Exception\DatabaseConnectionException;
use Fraym\Kernel;

set_exception_handler(static function (\Throwable $e): void {
    $logMessage = get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString();

    if ($e instanceof \Fraym\Exception\DatabaseQueryException) {
        $logMessage .= "\nQuery parameters: " . json_encode($e->getMaskedParameters(), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    error_log($logMessage);
    http_response_code(500);

    if (($_SERVER['HTTP_FRAYM_REQUEST'] ?? '') === 'true' || str_starts_with($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ')) {
        header('Content-Type: application/json');
        echo json_encode(['response' => 'error', 'response_text' => 'Internal server error']);
    } else {
        echo '<h1>500 Internal Server Error</h1>';
    }
});

try {
    Kernel::init();
} catch (DatabaseConnectionException $e) {
    error_log($e->getMessage());
    http_response_code(503);

    if (($_SERVER['HTTP_FRAYM_REQUEST'] ?? '') === 'true' || str_starts_with($_SERVER['HTTP_AUTHORIZATION'] ?? '', 'Bearer ')) {
        header('Content-Type: application/json');
        echo json_encode(['response' => 'error', 'response_text' => 'Database unavailable']);
    } else {
        echo '<h1>503 Service Unavailable</h1><p>Database connection error.</p>';
    }

    exit;
}
