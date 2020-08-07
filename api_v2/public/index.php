<?php
declare(strict_types=1);


error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
});

use Api\V2\Handlers\HttpErrorHandler;
use Api\V2\Handlers\ShutdownHandler;
use Api\V2\ResponseEmitter\ResponseEmitter;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

require __DIR__ . '/../../envPHP/load.php';

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

if (false) { // Should be set to true in production
	$containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

// Set up settings
$settings = require __DIR__ . '/../app/settings.php';
$settings($containerBuilder);

// Set up dependencies
$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);


// Build PHP-DI Container instance
$container = $containerBuilder->build();

$container->set('errorHandler', function ($c) use ($container) {
    return function ($request, $response, $exception) use ($c) {
        return $response->withStatus($exception->getCode())
            ->withHeader('Content-Type', 'application/json')
            ->write($exception->getMessage());
    };
}
);

// Instantiate the app
AppFactory::setContainer($container);
$app = AppFactory::create();

$callableResolver = $app->getCallableResolver();


// Register middleware
$middleware = require __DIR__ . '/../app/middleware.php';
$middleware($app);

// Register routes
$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

/** @var bool $displayErrorDetails */
$displayErrorDetails = $container->get('settings')['displayErrorDetails'];

// Create Request object from globals
$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// Create Error Handler
$responseFactory = $app->getResponseFactory();
$errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);
$errorHandler->setDisplayStackTrace($container->get('settings')['stackTraceInErrorResponse']);
$logger = $app->getContainer()->get(\Psr\Log\LoggerInterface::class);
$errorHandler->setLogger($logger);

// Create Shutdown Handler
$shutdownHandler = new ShutdownHandler( $request, $errorHandler, $displayErrorDetails);
register_shutdown_function($shutdownHandler);
// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Run App & Emit Response
$response = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);
