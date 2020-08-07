<?php


namespace Api\V2\Actions\Priv\Equipment\Radius;


use Api\V2\Actions\Action;
use envPHP\service\bindingsDB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;

class PostAuthAction extends Action
{
    protected $conf;
    function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->conf = getGlobalConfigVar('RADIUS');
        if(!$this->conf) {
            throw new \Exception("API for radius not configured");
        }
        if(!@$this->conf['enabled']) {
            throw new HttpForbiddenException($this->request, "API for radius disabled");
        }
    }

    protected function action(): Response
    {
        if(!$this->conf['post_auth_logging']) {
            return  $this->respondWithData(false);
        }
        $req =  $this->prepareData();
        try {
            $binding = bindingsDB::findBindingIp($req['user_mac_address'], $req['vlan_id'])['id'];
            dbConnPDO()->prepare("
            INSERT INTO eq_bindings_activity (created_at, updated_at, binding_id, request, response)
            VALUES (NOW(), NOW(), ?, ?, ?) 
            ON DUPLICATE KEY UPDATE updated_at = NOW(), request = ?, response = ?
        ")->execute([
                $binding,
                json_encode($req['request']),
                json_encode($req['response']),
                json_encode($req['request']),
                json_encode($req['response']),
            ]);
            $this->logger->debug("post-auth from radius:", $req);
        } catch (\Exception $e) {

        }
        return  $this->respondWithData($req);
    }

    protected function prepareData() {
        $data = $this->getFormData();
        if(preg_match($this->conf['parse_vlan_regex'], $data['request']['dhcp_server_name'], $m)) {
            $vlanId = $m[1];
        } else {
            $vlanId = 0 ;
        }
        return [
            'vlan_id' => $vlanId,
            'user_mac_address' => $data['request']['device_mac'],
            'dhcp_server_name' => $data['request']['dhcp_server_name'],
            'mikrotik_ip' => $data['request']['nas_ip'],
            'request' => $data['request'],
            'response' => $data['response'],
        ];
    }
}