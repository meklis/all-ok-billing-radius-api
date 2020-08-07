<?php

namespace envPHP\service;

use envPHP\classes\std;

/**
 * Created by PhpStorm.
 * User: Meklis
 * Date: 14.08.2017
 * Time: 22:20
 */
class bindings
{
    static protected $bindObj = null;

    static function delete($bindId)
    {
        dbConnPDO()->beginTransaction();
        try {
            $device = new bindingsDevice($bindId);
            $device->delBinding();
            self::bindDB()->deleteBinding($bindId);
        } catch (\Exception $e) {
            dbConnPDO()->rollBack();
            throw  new \Exception($e->getMessage(), 1, $e);
        }
        dbConnPDO()->commit();
        return true;
    }

    static function add($activationId, $ip, $mac, $switch, $port, $employeeId, $realIp = false, $allow_static = false)
    {
        \envPHP\classes\std::msg($activationId);
        \envPHP\classes\std::msg($ip);
        \envPHP\classes\std::msg($mac);
        \envPHP\classes\std::msg($switch);
        \envPHP\classes\std::msg($port);
        \envPHP\classes\std::msg($employeeId);
        dbConnPDO()->beginTransaction();
        if (!$ip) {
            $ip = self::getClearIP($switch, $realIp);
        }
        try {
            self::bindDB()->addBinding($activationId, $ip, $mac, $switch, $port, $employeeId, $allow_static );
            $bindId = dbConnPDO()->lastInsertId();
            $device = new bindingsDevice($bindId);
            $device->addBinding();
        } catch (\Exception $e) {
            dbConnPDO()->rollBack();
            throw  new \Exception($e->getMessage(), 1, $e);
        }
        dbConnPDO()->commit();
        return $bindId;
    }

    static function edit($bindId, $newIp, $newMac, $newSwitch, $newPort, $employeeId, $allow_static = false)
    {
        dbConnPDO()->beginTransaction();
        try {
            $device = new bindingsDevice($bindId);
            $device->editBindings($newIp, $newMac, $newSwitch, $newPort, $allow_static);
            self::bindDB()->editBinding($bindId, $employeeId, 0, $newIp, $newMac, $newSwitch, $newPort, $allow_static);
        } catch (\Exception $e) {
            dbConnPDO()->rollBack();
            throw new \Exception($e->getMessage(), 1, $e);
        }
        dbConnPDO()->commit();
        return true;
    }

    static function get($bindId)
    {
        return self::bindDB()->getBinding($bindId);
    }

    static protected function bindDB()
    {
        if (self::$bindObj) return self::$bindObj;
        self::$bindObj = new bindingsDB();
        return self::$bindObj;
    }

    static function getClearIP($switch, $real = false)
    {
        $data = null;
        $types = $real ? getGlobalConfigVar('BILLING')['free_ip_search']['real'] : getGlobalConfigVar('BILLING')['free_ip_search']['local'] ;
        if(count($types) === 0) {
            throw new \Exception("Типы IP для поиска не указаны в конфиге!!!");
        }
        foreach ($types as $type) {
            $data = dbConnPDO()->query("	SELECT INET_ATON(startIp) start, INET_ATON(stopIp) stop  FROM `equipment` e 
                    JOIN eq_vlan_equipment ev on ev.equipment = e.id
                    JOIN eq_vlan_neth vn on vn.vlan = ev.vlan 
                    JOIN eq_neth n on n.id = vn.neth 
                    WHERE n.type = '{$type}' and ip =  '$switch'");
            if ($data->rowCount() != 0) {
                 break;
            }
        }

        while ($d = $data->fetch()) {
            for ($i = $d['start']; $i <= $d['stop']; $i++) {
                $ip = long2ip($i);
                if (dbConnPDO()->query("SELECT id FROM eq_bindings WHERE ip = '$ip'")->rowCount() == 0) {
                    return $ip;
                }
            }
        }
        throw  new \Exception(__METHOD__ . "->Not found free local ip for $switch");
    }
}