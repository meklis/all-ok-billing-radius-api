<?php
declare(strict_types=1);

namespace Api\V2\Middleware;

use envPHP\service\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpUnauthorizedException;

class RealAddressMiddleware implements Middleware
{
    protected $user;
    static protected $token;
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $query_params = $request->getQueryParams();
        if(!isset($query_params['ACCESS_TYPE']) || $query_params['ACCESS_TYPE'] != 'private' || !isset($query_params['REMOTE_ADDR'])) {
            $query_params['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        }
        return $handler->handle($request->withQueryParams($query_params));
    }

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
