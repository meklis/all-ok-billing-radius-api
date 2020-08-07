<?php
declare(strict_types=1);

namespace Api\V2\Middleware;

use IPv4\SubnetCalculator;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpForbiddenException;

class AuthByIpMiddleware implements Middleware
{
    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $trustedIps = getGlobalConfigVar('API_TRUSTED_IPS');
        $allowed = false;
        foreach ($trustedIps['trusted'] as $ip) {
            $d = explode("/", $ip);
            if(count($d) == 1) {
                $ip = $d[0];
                $mask = 32;
            } else if (count($d) == 2) {
                $ip = $d[0];
                $mask = $d[1];
            } else {
                throw new \Exception("Incorrect trusted IPs in configuration");
            }
            $sub = new SubnetCalculator($ip, $mask);
            if($sub->isIPAddressInSubnet($_SERVER[$trustedIps['real_addr_param_name']])) {
                $allowed = true;
                break;
            }
        }
        if(!$allowed) {
            throw new HttpForbiddenException($request, "IP {$_SERVER[$trustedIps['real_addr_param_name']]} not allowed for current method");
        }
        return $handler->handle($request);
    }

}
