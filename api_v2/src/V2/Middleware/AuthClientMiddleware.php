<?php
declare(strict_types=1);

namespace Api\V2\Middleware;

use envPHP\service\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpUnauthorizedException;

class AuthClientMiddleware implements Middleware
{
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
        $client_id = $this->validateToken($token);
        if($client_id <= 0) {
            throw new HttpForbiddenException($request, "Unknown auth key");
        }
        $query_params['CLIENT_ID'] = $client_id;
        $query_params['USER_ID'] = getGlobalConfigVar('BASE')['lc_user_id'];
        $query_params['ACCESS_TYPE'] = 'client';
        return $handler->handle($request->withQueryParams($query_params));
    }
    protected function validateToken($tokenUUID) {
        if($info = $this->sql->query("SELECT client_id, if(expired_at > NOW(), 1 , 0) expired  FROM client_tokens WHERE token = '{$tokenUUID}'")->fetch_assoc()) {
            if($info['client_id']) {
                return $info['client_id'];
            } else {
                return -1;
            }
        }
        return -2;
    }
}
