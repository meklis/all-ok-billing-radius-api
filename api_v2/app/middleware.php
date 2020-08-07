<?php
declare(strict_types=1);

use Api\V2\Middleware\CorsMiddleware;
use Api\V2\Middleware\RealAddressMiddleware;
use Api\V2\Middleware\SessionMiddleware;
use Slim\App;

return function (App $app) {
    $app->add(CorsMiddleware::class);
    $app->add(SessionMiddleware::class);
};
