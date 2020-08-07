<?php
declare(strict_types=1);
use Api\V2\Middleware\AuthByIpMiddleware;
use Api\V2\Middleware\RealAddressMiddleware;
use Api\V2\Actions\Priv\Equipment\Radius\GetRadiusInfoAction;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use \Api\V2\Actions\Priv\Equipment\Radius\PostAuthAction;

return function (App $app) {
    //trusted by ip routes
    $app->group('/v2/trusted', function (Group $group) {
        $group->group('/equipment', function (Group $group) {
            $group->group('/radius', function (Group $group) {
                $group->post('/request', GetRadiusInfoAction::class);
                $group->post('/post-auth', PostAuthAction::class);
            });
        });
    })->add(RealAddressMiddleware::class)->add(AuthByIpMiddleware::class);
};
