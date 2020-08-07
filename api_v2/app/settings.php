<?php
declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Logger;

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        'settings' => [
            'displayErrorDetails' => true, // Should be set to false in production
            'stackTraceInErrorResponse' => false,
            'logger' => [
                'name' => 'api.v2',
                'path' => isset($_ENV['docker']) ? 'php://stdout' : getGlobalConfigVar('BASE')['logDir'] . '/apiv2.log',
                'level' => Logger::DEBUG,
            ],
            'post_auth_logging' => true,
        ],
    ]);
};
