<?php

namespace envPHP\service;

use envPHP\classes\std;


/**
 * Created by PhpStorm.
 * User: Meklis
 * Date: 05.08.2017
 * Updated: 05.02.2020
 * Time: 17:57
 */
class bindingsDevice
{
    static protected $connections = [];
    protected $currentBinding = [];

    public function __construct($bindId)
    {
        $this->currentBinding = bindingsDB::getBinding($bindId);
    }

    public function editBindings($ip = "", $mac = "", $switch = "", $port = "", $allow_static = false)
    {
        if ($this->currentBinding['status'] == 'Заморожена') return true;
        //Определим параметры на изменения
        $update['switch'] = $switch && $switch !== $this->currentBinding['switch'] ? $switch : $this->currentBinding['switch'];
        $update['ip'] = $ip && $ip !== $this->currentBinding['ip'] ? $ip : $this->currentBinding['ip'];
        $update['mac'] = $mac && $mac !== $this->currentBinding['mac'] ? $mac : $this->currentBinding['mac'];
        $update['port'] = $port && $port !== $this->currentBinding['port'] ? $port : $this->currentBinding['port'];

        if (($ip && $ip != $this->currentBinding['ip']) || ($mac && $mac != $this->currentBinding['mac'])) {
            //Delete old biding on core
            $this->coreDeReg($this->currentBinding['mac'], $this->currentBinding['ip'], "", $allow_static);

            //Add new binding on core
            $interface = bindingsDb::getInterfaceName($update['ip'], $update['switch']);
            $this->coreReg($update['mac'], $update['ip'], $interface, $this->currentBinding['agreement'], $this->currentBinding['speed'], $allow_static);
            $this->currentBinding['ip'] = $update['ip'];
            $this->currentBinding['mac'] = $update['mac'];
        }

        //Определим параметры для работы с по работе с железом доступа
        if (($switch && $port) && ($switch != $this->currentBinding['switch'] || $port != $this->currentBinding['port'])) {
            if (bindingsDB::isDeviceConfigurable($this->currentBinding['switch'])) {
                $vl = bindingsDB::getVlanBySwitch($this->currentBinding['switch'], getGlobalConfigVar('BILLING')['vlans_types']['fake']);
                $this->accessDeReg($vl, $this->currentBinding['switch'], $this->currentBinding['port']);
                $vl = bindingsDB::getVlanBySwitch($update['switch'], getGlobalConfigVar('BILLING')['vlans_types']['inet']);
                $this->accessReg($vl, $update['switch'], $update['port'], $this->currentBinding['agreement']);
            }
        }
    }

    public function delBinding($enableMikrotik = true)
    {
        $vl = bindingsDB::getVlanBySwitch($this->currentBinding['switch'], getGlobalConfigVar('BILLING')['vlans_types']['fake']);
        if (!$vl) throw  new \Exception(__METHOD__ . " Fake vlan not found");

        $mac = \envPHP\classes\std::checkParam('mac', $this->currentBinding['mac']);
        $switch = \envPHP\classes\std::checkParam('ip', $this->currentBinding['switch']);
        $port = \envPHP\classes\std::checkParam('port', $this->currentBinding['port']);
        $ip = \envPHP\classes\std::checkParam('ip', $this->currentBinding['ip']);

        //Work with mikrotik
        if ($enableMikrotik) {
            $this->coreDeReg($mac, $ip, "", $this->currentBinding['allow_static']);
        }

        $countBinds = 0;
        if (getGlobalConfigVar('BILLING')['change_vlan_on_port']['calculate_bindings']) {
            $countBinds = bindingsDB::getCountBindingsOnPort($this->currentBinding['switch'], $this->currentBinding['port'], $this->currentBinding['id']);
        }
        if ($countBinds == 0) {
            try {
                if (bindingsDB::isDeviceConfigurable($this->currentBinding['switch'])) {
                    $this->accessDeReg($vl, $switch, $port);
                } else {
                    \envPHP\classes\std::msg(__METHOD__ . "-> Device {$this->currentBinding['switch']} not configurable, ignoring...");
                }
            } catch (\Exception $e) {
                $interface = bindingsDB::getInterfaceName($ip, $switch);
                $this->coreReg($mac, $ip, $interface, $this->currentBinding['agreement'], $this->currentBinding['speed'],  $this->currentBinding['allow_static']);
                throw new \Exception(__METHOD__ . " -> " . $e->getMessage());
            }
        } else {
            \envPHP\classes\std::msg(__METHOD__ . "-> Exist other active bindings on this port ({$this->currentBinding['switch']}:{$this->currentBinding['port']})");
        }
        return $this;
    }

    public function addBinding($enableMikrotik = true, $switch = "", $port = "" )
    {
        if (!$switch) $switch = $this->currentBinding['switch'];
        if (!$port) $port = $this->currentBinding['port'];
        $allow_static = $this->currentBinding['allow_static'];
        $ip = \envPHP\classes\std::checkParam('ip', $this->currentBinding['ip']);
        $mac = \envPHP\classes\std::checkParam('mac', $this->currentBinding['mac']);

        //Work with mikrotik
        $interface = 0;
        if ($enableMikrotik) {
            $interface = bindingsDB::getInterfaceName($ip, $this->currentBinding['switch']);
            $this->coreReg($mac, $ip, $interface, $this->currentBinding['agreement'], $this->currentBinding['speed'], $allow_static);
        }
        std::msg("Success registration on core");
        //Work with access device
        try {
            if (bindingsDB::isDeviceConfigurable($switch)) {
                std::msg("Configuring access device is enabled");
                $vl = bindingsDB::getVlanBySwitchAbonIp($switch, getGlobalConfigVar('BILLING')['vlans_types']['inet'], $this->currentBinding['ip']);
                if (!$vl) throw  new \Exception(__METHOD__ . " INET vlan not found by params: ip = {$this->currentBinding['ip']}, switch = {$this->currentBinding['switch']}");
                $this->accessReg($vl, $switch, $port, $this->currentBinding['agreement']);
            } else {
                \envPHP\classes\std::msg(__METHOD__ . "-> Device {$switch} not configurable, ignoring...");
            }
        } catch (\Exception $e) {
            //Delete writed rules on mikrotik if exist error on access device
            std::msg("Error configure access device, rollback on core device");
            if ($enableMikrotik) {
                $this->coreDeReg($mac, $ip,$interface, $allow_static);
            }
            throw new \Exception($e->getMessage());
        }
        return $this;
    }

    protected static function getConnect($ip)
    {
        if (isset(self::$connections[$ip])) {
            return self::$connections[$ip];
        } else {
            $swInf = bindingsDB::getDeviceAccess($ip);
            $connect = new \envPHP\classes\AccessSwitcherCore($ip, $swInf['community'], $swInf['login'], $swInf['password']);
            self::$connections[$ip] = $connect;
            return $connect;
        }
    }

    /**
     * @param string $ip
     * @return \envPHP\classes\CoreSwitcherCore
     * @throws \Exception
     */
    protected static function getCoreConnect($ip = "")
    {

        \envPHP\classes\std::msg("Selecting router for ip $ip");
        $swInf = bindingsDB::getDeviceCore($ip, getGlobalConfigVar('BILLING')['devices']['core_levels']);
        if (!$swInf) {
            \envPHP\classes\std::msg(__METHOD__ . " -> Core for ip $ip not found");
            throw new \Exception(__METHOD__ . " -> Core for ip $ip not found");
        }
        \envPHP\classes\std::msg("Selecting router for ip $ip, router: " . json_encode($swInf));
        if (isset(self::$connections[$swInf['ip']])) {
            return self::$connections[$swInf['ip']];
        }
        $connect = new \envPHP\classes\CoreSwitcherCore($swInf['ip'], $swInf['community'], $swInf['login'], $swInf['password']);
        self::$connections[$swInf['ip']] = $connect;
        return $connect;
    }

    protected function coreReg($mac, $ip, $interface, $agreement, $speed, $allow_static = false)
    {
        //Arp adding control
        if (getGlobalConfigVar('BILLING')['static_arp']['enabled'] || $allow_static) {
            try {
                self::getCoreConnect($ip)->delArpIp($ip, $interface);
            } catch (\Exception $e) {
                std::msg("Error - old not found by MAC:{$ip}- {$e->getMessage()}");
            }
            self::getCoreConnect($ip)->addArpIp($mac, $ip, $interface, $agreement);
        }

        //Address list adding control
        if (getGlobalConfigVar('BILLING')['address_lists']['enabled']) {
            std::msg("try add ip to address list");
            $addressListName = getGlobalConfigVar('BILLING')['address_lists']['list_name'];
            try {
                self::getCoreConnect($ip)->removeFromAddressList($addressListName, $ip);
            } catch (\Exception $e) {
                std::msg("try delete not existed ip from address list - {$e->getMessage()}");
            }
            self::getCoreConnect($ip)->addToAddressList($addressListName, $ip, $agreement);
        }

        //Static lease control
        if (getGlobalConfigVar('BILLING')['static_lease']['enabled']) {
            std::msg("try add static lease");
            try {
                std::msg("TRY DELETE STATIC LEASE");
                self::getCoreConnect($ip)->delStaticLease($mac, $ip);
            } catch (\Exception $e) {
                std::msg("try delete not existed ip from static lease - {$e->getMessage()}");
            }
            self::getCoreConnect($ip)->addStaticLease($mac, $ip, $interface, $agreement);
        }

        //Queue control
        if (getGlobalConfigVar('BILLING')['simple_queue']['enabled']) {
            std::msg("Try set simple queue");
            try {
                self::getCoreConnect($ip)->removeQueueSpeed($ip, $this->currentBinding['speed']);
            } catch (\Exception $e) {
                std::msg("error from core when queue deleting on add binding - {$e->getMessage()}");
            }
            self::getCoreConnect($ip)->setQueueSpeed($ip, $speed, $agreement);
        }
    }

    protected function coreDeReg($mac, $ip, $interface, $allow_static = false)
    {
        //Arp adding control
        if (getGlobalConfigVar('BILLING')['static_arp']['enabled'] || $allow_static) {
            std::msg("Try delete static arp");
            try {
                self::getCoreConnect($ip)->delArpIp($ip, $interface);
            } catch (\Exception $e) {
                std::msg("ERROR DELETE ARP WITH $ip");
            }
        }

        //Static lease control
        if (getGlobalConfigVar('BILLING')['static_lease']['enabled']) {
            std::msg("Try delete lease");
            $addressListName = getGlobalConfigVar('BILLING')['address_lists']['list_name'];
            self::getCoreConnect($ip)->delStaticLease($mac, $ip);
        }

        //Address list adding control
        if (getGlobalConfigVar('BILLING')['address_lists']['enabled']) {
            std::msg("Try delete ip from address list ");
            $addressListName = getGlobalConfigVar('BILLING')['address_lists']['list_name'];
            self::getCoreConnect($ip)->removeFromAddressList($addressListName, $ip);
        }

        //Queue control
        if (getGlobalConfigVar('BILLING')['simple_queue']['enabled']) {
            std::msg("Try delete from simple queue");
            self::getCoreConnect($ip)->removeQueueSpeed($ip, $this->currentBinding['speed']);
        }
    }

    protected function accessReg($vlan, $switch, $port, $agreement)
    {
        if (getGlobalConfigVar('BILLING')['change_vlan_on_port']['enabled']) {
            std::msg("Try change vlan on access device");
            $this->accessSetVlan($vlan, $switch, $port);
        }
        if (getGlobalConfigVar('BILLING')['set_descr_on_port']['enabled']) {
            std::msg("Try set port description on access device");
            self::getConnect($switch)->setDescription($port, $agreement);
        }
        return $this;
    }

    protected function accessSetVlan($vlan, $switch, $port)
    {
        $vlans = self::getConnect($switch)->getVlans();
        if (!in_array($vlan, $vlans)) {
            throw new \Exception("Not found $vlan on device $switch", 404);
        }
        self::getConnect($switch)->setUntagVidOnPort($port, $vlan);
        return $this;
    }

    protected function accessDeReg($vlan, $switch, $port)
    {
        if (getGlobalConfigVar('BILLING')['change_vlan_on_port']['enabled']) {
            $this->accessSetVlan($vlan, $switch, $port);
        }
        return $this;
    }

}