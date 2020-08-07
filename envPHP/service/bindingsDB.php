<?php

namespace envPHP\service;

use envPHP\classes\std;

/**
 * Created by PhpStorm.
 * User: Meklis
 * Date: 29.07.2017
 * Time: 21:00
 */
class bindingsDB
{

    /**
     * Метод возвращает одну уникальную привязку  по базе
     * @param string $id
     * @param string $ip
     * @param string $mac
     * @param string $switch
     * @param string $port
     * @return array
     * @throws Exception
     */
    public static function getBinding($id = "", $ip = "", $mac = "", $switch = "", $port = "")
    {
        $WHERE = " b.id != 0 ";
        if ($ip) $ip = \envPHP\classes\std::checkParam('ip', $ip);
        if ($switch) $switch = \envPHP\classes\std::checkParam('ip', $switch);
        if ($mac) $mac = \envPHP\classes\std::checkParam('mac', $mac);
        if ($id) $WHERE .= " and b.id = '$id' ";
        if ($ip) $WHERE .= " and b.ip = '$ip' ";
        if ($mac) $WHERE .= " and b.mac = '$mac' ";
        if ($switch) $WHERE .= " and eq.ip = '$switch' ";
        if ($port) $WHERE .= " and b.port = '$port' ";
        $data = dbConnPDO()->query("
                        SELECT b.id
                        , cl.agreement
                        , CONCAT(pr.`name`, ' (',pr.price_day,'грн/день)') price 
                        , addr.full_addr
                        ,b.ip
                        ,b.port
                        ,b.mac
                        ,eq.ip switch
                        ,m.`name` model 
                        ,IF(act.time_stop is null, 'Активна', 'Заморожена') status 
                        ,pr.speed
                        ,m.configurable
                        ,b.allow_static
                        FROM `eq_bindings` b 
                        JOIN equipment eq on eq.id = b.switch
                        JOIN equipment_models m  on m.id = eq.model
                        JOIN client_prices act on act.id = b.activation 
                        JOIN clients cl on cl.id = act.agreement
                        JOIN addr on addr.id = cl.house
                        JOIN bill_prices pr on pr.id = act.price
                        WHERE $WHERE LIMIT 1");
        if(!$data) {
            std::msg("[QUERY] ". "
                        SELECT b.id
                        , cl.agreement
                        , CONCAT(pr.`name`, ' (',pr.price_day,'грн/день)') price 
                        , addr.full_addr
                        ,b.ip
                        ,b.port
                        ,b.mac
                        ,eq.ip switch
                        ,m.`name` model 
                        ,IF(act.time_stop is null, 'Активна', 'Заморожена') status 
                        ,pr.speed
                        ,m.configurable
                        FROM `eq_bindings` b 
                        JOIN equipment eq on eq.id = b.switch
                        JOIN equipment_models m  on m.id = eq.model
                        JOIN client_prices act on act.id = b.activation 
                        JOIN clients cl on cl.id = act.agreement
                        JOIN addr on addr.id = cl.house
                        JOIN bill_prices pr on pr.id = act.price
                        WHERE $WHERE LIMIT 1");

        }
        if ($data->rowCount() == 0) throw new \Exception(__METHOD__ . "->" . "Binding not found");
        return $data->fetch();
    }

    /**
     * @param $id
     * @param $employee
     * @param int $activId
     * @param string $ip
     * @param string $mac
     * @param string $switch
     * @param string $port
     * @return bool
     * @throws \Exception
     */
    public static function editBinding($id, $employee, $activId = 0, $ip = "", $mac = "", $switch = "", $port = "", $allow_static = false)
    {
        $sql = dbConnPDO();
        if ($ip) $ip = \envPHP\classes\std::checkParam('ip', $ip);
        if ($switch) $switch = \envPHP\classes\std::checkParam('ip', $switch);
        if ($mac) $mac = \envPHP\classes\std::checkParam('mac', $mac);
        if ($switch) {
            $switch = $sql->query("SELECT id FROM equipment WHERE ip = '$switch'")->fetch()['id'];
            if (!$switch) throw new \Exception(__METHOD__ . "->" . "New switch not found in equipment");
        }
        //Обновление по базе
        $SETTER = "SET ";
        if ($ip) $SETTER .= " ip = '$ip',";
        if ($switch) $SETTER .= " switch = '$switch',";
        if ($mac) $SETTER .= " mac = '$mac',";
        if ($port) $SETTER .= " port = '$port',";
        if ($employee) $SETTER .= " employee = '$employee',";
        if ($activId) $SETTER .= " activation = $activId,";
        $SETTER .= $allow_static ? " allow_static = 1," :  " allow_static = 0," ;
        $SETTER = trim($SETTER, ",");
        $test = $sql->query("UPDATE eq_bindings $SETTER WHERE id = $id ");
        if (!$test) throw new \Exception(__METHOD__ . "->" . $sql->error);
        return true;
    }

    public static function deleteBinding($id)
    {
        $sql = dbConnPDO();
        $test = $sql->query("DELETE  FROM eq_bindings WHERE id = $id");
        if (!$test) throw new \Exception("Ошибка работы с БД:" . $sql->error);
        return true;
    }

    public static function addBinding($actId, $ip, $mac, $switch, $port, $employee = 0, $allow_static = false)
    {
        $sql = dbConnPDO();
        if (!$switch) throw new \Exception(__METHOD__ . "->Switch is required parameter", 400);
        if (!$actId) throw new \Exception(__METHOD__ . "->Activation is required parameter", 400);
        if (!is_int($switch)) {
            $switch = $sql->query("SELECT id FROM service.equipment WHERE ip = '$switch'")->fetch()['id'];
        }
        if (!$switch) throw new \Exception("Свитч не найден, введите корректный IP");

        if ($sql->query("SELECT id FROM client_prices WHERE id = $actId")->rowCount() <= 0) {
            throw new \Exception("Указанная активация не найдена, проверьте корректность и повторите");
        }
        $mac = \envPHP\classes\std::checkParam('mac', $mac);
        $ip = \envPHP\classes\std::checkParam('ip', $ip);
        $port = \envPHP\classes\std::checkParam('port', $port);

        if($sql->query("SELECT ip FROM eq_bindings WHERE ip = '$ip'")->rowCount() !== 0) {
            throw new \Exception("Указанный IP $ip уже занят, используйте другой");
        }
        $static = $allow_static ? 1 : 0;
        $test = $sql->query("INSERT INTO eq_bindings (activation, switch, port, mac, ip, employee, allow_static) 
          VALUES ($actId, '$switch', $port, '$mac', '$ip', $employee, $static)");
        if (!$test) throw new \Exception(__METHOD__ . "->" . $sql->error);
        return $sql->lastInsertId();
    }

    public static function getBindingsByActivation($actId, $frosted = false)
    {
        $actId = \envPHP\classes\std::checkParam('port', $actId);
        if ($frosted) {
            $WHERE = " and act.time_stop is not null";
        } else {
            $WHERE = " and act.time_stop is null";
        }
        $data = dbConnPDO()->query("SELECT tb.id trinity, eb.id equipment, pr.work_type `type`
                    FROM client_prices act 
                    JOIN clients cl on cl.id = act.agreement
                    JOIN addr on addr.id = cl.house
                    JOIN bill_prices pr on pr.id = act.price
                    LEFT JOIN eq_bindings eb on eb.activation = act.id
                    LEFT JOIN trinity_bindings tb on tb.activation = act.id
                    WHERE (tb.id is not null or eb.id is not null) and act.id = $actId $WHERE");
        std::msg("SELECT tb.id trinity, eb.id equipment, pr.work_type `type`
                    FROM client_prices act 
                    JOIN clients cl on cl.id = act.agreement
                    JOIN addr on addr.id = cl.house
                    JOIN bill_prices pr on pr.id = act.price
                    LEFT JOIN eq_bindings eb on eb.activation = act.id
                    LEFT JOIN trinity_bindings tb on tb.activation = act.id
                    WHERE (tb.id is not null or eb.id is not null) and act.id = $actId $WHERE");
        if ($data->rowCount() == 0) return false;
        $RESPONSE = [];
        while ($d = $data->fetch()) {
            $d['id'] = $d['type'] == 'inet' ? $d['equipment'] : $d['trinity'];
            $RESPONSE[] = $d;
        }
        return $RESPONSE;
    }

    public static function isDeviceConfigurable($deviceIp)
    {
        if (dbConnPDO()->query("SELECT eq.* FROM equipment eq 
                                JOIN `equipment_models` m on m.id = eq.model 
                                WHERE m.configurable = 'YES' and eq.ip = '$deviceIp'")->rowCount() != 0) {
            return true;
        }
        return false;
    }

    public static function getVlanBySwitch($switchIp, $vlanTypes)
    {
        $vl = dbConnPDO()->query("SELECT vl.vlan FROM `eq_vlans` vl
        JOIN eq_vlan_equipment ve on ve.vlan = vl.id
        JOIN equipment eq on eq.id = ve.equipment 
        WHERE vl.type in (" . join(",", $vlanTypes) . ") and eq.ip = '{$switchIp}'
        LIMIT 1 
        ;")->fetch()['vlan'];
        return $vl;
    }

    public static function getVlanBySwitchAbonIp($switchIp, $vlanTypes, $bindIp)
    {
        $vl = dbConnPDO()->query("SELECT vl.vlan FROM `eq_vlans` vl
            JOIN eq_vlan_equipment ve on ve.vlan = vl.id
            JOIN equipment eq on eq.id = ve.equipment 
            JOIN eq_vlan_neth vn on vn.vlan = vl.id
            JOIN eq_neth n on n.id = vn.neth
            WHERE vl.type in (" . join(",", $vlanTypes) . ") and eq.ip = '{$switchIp}'
            and (INET_ATON('{$bindIp}') BETWEEN INET_ATON(n.startIp) and INET_ATON(n.stopIp)) ")->fetch()['vlan'];
        return $vl;
    }

    public static function getCountBindingsOnPort($switchIp, $port, $excludeBindingId = 0)
    {
        return dbConnPDO()->query("SELECT b.* FROM eq_bindings b JOIN client_prices pr on pr.id = b.activation 
                                            WHERE pr.time_stop is null  
                                            and switch = (SELECT id FROM equipment WHERE ip = '{$switchIp}') and port = '{$port}'
                                            and b.id != {$excludeBindingId}")->rowCount();
    }

    public static function getDeviceAccess($ip_or_devId)
    {
        $res =  dbConnPDO()->query("SELECT eq.ip,  m.name model, ac.login, ac.`password`, ac.community
                    FROM equipment eq 
                    JOIN equipment_access ac on ac.id = eq.access
                    JOIN equipment_models m on m.id = eq.model
                    WHERE (eq.ip = '$ip_or_devId' or eq.id = '$ip_or_devId')");

        return $res->fetch();
    }

    public static function getDeviceCore($userIp, $coreTypes)
    {
        $coreTypes = join(",", $coreTypes);
        $res  = dbConnPDO()->query("SELECT e.ip, a.login, a.`password`, a.community
                    FROM eq_neth n 
                    JOIN eq_vlan_neth vn on vn.neth = n.id
                    JOIN eq_vlans v on v.id = vn.vlan
                    JOIN eq_vlan_equipment ve on ve.vlan = v.id
                    JOIN equipment e on e.id = ve.equipment
                    JOIN equipment_access a on a.id = e.access
                    WHERE (INET_ATON('$userIp') BETWEEN INET_ATON(n.startIp) and INET_ATON(n.stopIp)) and e.`group` in ($coreTypes)");

        return $res->fetch();
    }

    public static function getInterfaceName($userIp, $switch)
    {
        $interface = dbConnPDO()->query("SELECT DISTINCT v.`name`
                FROM eq_vlans v
                JOIN eq_vlan_equipment ev on ev.vlan = v.id
                JOIN equipment eq on eq.id = ev.equipment
                JOIN eq_vlan_neth nv on nv.vlan = v.id 
                JOIN eq_neth n on n.id = nv.neth 
                WHERE INET_ATON('$userIp') BETWEEN INET_ATON(n.startIp) and INET_ATON(n.stopIp)
                and eq.ip = '$switch' LIMIT 1")->fetch();
        if (!$interface['name']) throw new \Exception("Interface name not found in DB(vlan+switch+netsh)");
        return $interface['name'];
    }

    public static function findBindingIp($userMac, $vlanId = 0, $deviceMac = "", $devicePort = 0) {
        $WHERE = "";
        $prepared = [];
        if($userMac) {
            $WHERE .= " and b.mac = ?";
            $prepared[] = $userMac;
        }
        if($vlanId) {
            $WHERE .= " and vl.vlan = ?";
            $prepared[] = $vlanId;
        }
        if($deviceMac) {
            $WHERE .= " and e.mac = ?";
            $prepared[] = $deviceMac;
        }
        if($devicePort) {
            $WHERE .= " and b.port = ?";
            $prepared[] = $devicePort;
        }
        $info = dbConnPDO()->prepare("SELECT DISTINCT b.ip user_ip, b.id, b.mac user_mac, if(time_stop is null, 'active', 'frosted') `status` 
                FROM eq_bindings b 
                JOIN equipment e on e.id = b.switch
                JOIN eq_vlan_equipment ve on ve.equipment = e.id 
                JOIN eq_vlans vl on vl.id = ve.vlan 
                LEFT JOIN client_prices p on p.id = b.activation 
                WHERE 1=1 $WHERE");
        $info->execute($prepared);

        if($info->rowCount() == 0) {
            throw new \Exception("Not found user IP address by parameters");
        } elseif ($info->rowCount() > 1) {
            throw new \Exception("Found multiple ip addresses by paramaters. Must be unique value");
        }
        return $info->fetch();
    }
}