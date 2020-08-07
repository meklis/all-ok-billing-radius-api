<?php


namespace Api\V2\Actions\Priv\Equipment\Radius;


use Api\V2\Actions\Action;
use envPHP\service\bindingsDB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;

class GetRadiusInfoAction extends Action
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
        $req = $this->prepareData();
        $data = [
            'user_ip' => '',
            'status' => 'status',
        ];
        try {
            $data = bindingsDB::findBindingIp($req['user_mac_address'], $req['vlan_id']);
        } catch (\Exception $e) {
            $this->logger->notice("Store returned err - " . $e->getMessage(), $req);
        }
        if($data['user_ip'] && $data['status'] == 'active') {
            $response = [
                'ip_address' => $data['user_ip'],
                'lease_time_sec' => $this->conf['lease_time']['ip'],
            ];
        } else {
            $response = [
                'pool_name' => $req['dhcp_server_name'],
                'lease_time_sec' => $this->conf['lease_time']['pool'],
            ];
        }
        $this->logger->debug("response to radius", $response);
        return  $this->respondWithData($response);
    }

    protected function prepareData() {
        $data = $this->getFormData();
        if(preg_match($this->conf['parse_vlan_regex'], $data['dhcp_server_name'], $m)) {
            $vlanId = $m[1];
        } else {
            $vlanId = 0 ;
        }
        return [
            'vlan_id' => $vlanId,
            'user_mac_address' => $data['device_mac'],
            'dhcp_server_name' => $data['dhcp_server_name'],
            'mikrotik_ip' => $data['nas_ip'],
        ];
    }
}