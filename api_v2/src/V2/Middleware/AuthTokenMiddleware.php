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

class AuthTokenMiddleware implements Middleware
{
    protected $user;
    static protected $token;
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $token = "";
        $tokenHeader = $request->getHeader('X-Auth-Key');
        if(count($tokenHeader) != 0 ) {
            $token = $tokenHeader[0];
        } else if (isset($request->getQueryParams()['x-auth-key'])) {
            $token = $request->getQueryParams()['x-auth-key'];
        } else {
            throw new HttpBadRequestException($request, "X-Auth-Key header not setted. You must set token for private methods.");
        }
        $userId = $this->user->validateToken($token);
        if($userId < 0) {
            throw new HttpUnauthorizedException($request, "Incorrect user token");
        }
        $query_params = $request->getQueryParams();
        $query_params['USER_ID'] = $userId;
        $query_params['ACCESS_TYPE'] = 'private';
        return $handler->handle($request->withQueryParams($query_params));
    }

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
