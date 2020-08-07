<?php
namespace envPHP\classes;
/**
 * Created by PhpStorm.
 * User: Meklis
 * Date: 13.04.2017
 * Time: 9:46
 */
class codes  
{
    const ERR_OPEN_SOCKET = 20; //Ошибка открытия сокета Telnet
    const SUCCESS_OPEN_SOCKET = 21; //Сокет успешно открыт
    const CLOSED_NOT_EXISTED_SOCKET = 22; //Попытка закрыть несуществующий сокет
    const DEV_NOT_SUPPORT_METHOD = 23; //Данный метод не поддерживается текущим классом
    const ERR_RESP_SNMP = 24; //Устройство недоступно по SNMP
    const DEV_NO_RESPONSE = 25; //Устройство недоступно(Проверка по SNMP + PING)
    const PORT_NO_MACS = 26; //Устройство недоступно(Проверка по SNMP + PING)
    const PORT_ERR_CHECK = 80; //Устройство недоступно(Проверка по SNMP + PING)
    const ERR_BINDING_CHECK = 27; //Устройство недоступно(Проверка по SNMP + PING)
    const DEV_SUCCESS_CHECKED = 28; //Успешно проверили доступность устройства
    const PORT_NO_LINK = 29; //Успешно проверили доступность устройства
    const PORT_LOW_SPEED = 30; //Успешно проверили доступность устройства
    const PORT_DISABLED = 31; //Успешно проверили доступность устройства
    const PORT_OK = 32; //Успешно проверили доступность устройства
    const ERR_CHECK_CABLE_UPLINK = 34; // Аплинк порт - диагностику проводить запрещено
    const ACCESS_INCORRECT_VLAN_ON_PORT = 35; // Неккоректный влан на порту
    const ACCESS_PORT_ERR_CHECK = 33;
    const ACCESS_SUCCESS_CHECK_ACCESS = 36; // Доступы успешно проверены
    const ACCESS_NO_DENIED_PROFILES = 38; // Нет ограничений на порту - потенциальный нессан
    const ACCESS_NO_DHCP_PROFILES = 39; // Нет профайлов ACL 4 0.0.0.0, абонент не сможет получить IP
    const ACCESS_NO_ALLOW_PROFILES = 40; // Нет разрешаяющих профайлов
    const ACCESS_VLAN_NOT_EXIST = 57; //Это ОНУ
    const ACCESS_NO_FAKE_PROFILES = 99;//Нет 9-го профайла
    const DIAG_FALSE = 42; // Ошибка проверки кабеля
    const DIAG_UPLINK = 41; // Попытка продиагностировать аплинк
    const DIAG_CABLE_OK = 43; //С кабелем все впорядке
    const DIAG_CABLE_PROBLEM = 44; //Есть проблемы с линком
    const DIAG_NO_INIT = 60; //Диагностика не инициализирована
    const DIAG_NO_CABLE = 79; //Диагностика - нет кабеля в порту свитча
    const FDB_ERROR_CHECK = 45; //Ошибка проверки FDB на порту
    const FDB_NO_MACS = 46; //На порту мак-адресов не найдено
    const FDB_OK = 47; //Успешно получили FDB по порту
    const DEV_TELNET_ERR_OPEN = 48; //Ошибка подключения по telnet
    const DEV_TELNET_ERR_LOGIN = 49; //Ошибка подключения по телнет, авторизация неуспешна
    const ONU_NOT_EXISTS = 50;//ОНУ не существует
    const ONU_SNMP_FOUND = 51; //ОНУ успешно найдена по SNMP
    const SUCCESS = 52; //
    const FAIL = 53; //
    const THIS_ONU = 54; //Это ОНУ
    const ONU_OFFLINE = 55; //Это ОНУ
    const ONU_ONLINE = 56; //Это ОНУ
    const VLAN_INTERNET = 58;
    const VLAN_FAKE = 59;
    const IP_DETAIL_NO_ARP = 70; //НЕТ АРПов, но есть на свитче
    const IP_DETAIL_NOT_USED = 71; //Есть в привязках, но не используется
    const IP_DETAIL_OK = 72; //Есть в привязках, но не используется
    const IP_DETAIL_WRONG_MAC = 73; //Есть в привязках, но не используется
    const IP_DETAIL_FREEMIUM = 74; //Есть в привязках, но не используется
    const IP_DETAIL_INCORRECT_LEASE = 75; //Есть в привязках, но не используется
    const IP_DETAIL_PAYED_FREEMIUM = 76;
    const IP_DETAIL_OTHER_AGREEMENT = 77;
    const IP_DETAIL_UNKNOWN_DEVICE = 78;
    const IP_DETAIL_NO_ARP_WIFI = 91;
    const IP_DETAIL_OTHER_ERR = 92;
    const SNMP_ERR_SET = 93; //Ошибка установки
}