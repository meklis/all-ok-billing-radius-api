<?php
namespace envPHP\classes;
/**
 * Created by PhpStorm.
 * User: meklis
 * Date: 17.01.2017
 * Time: 19:53
 */
class std
{
    static $error = "";
    static $errno = "";
    static $headers = null;

    /**
     * Определяет модель устройства по sysDescr.0 b записывает в memcached[ip]['model'] = model
     *
     * @param $ip IP свитча
     * @param $community коммунити свитча
     * @return bool успешность проверки. при false - не удалось опросить устройство
     */
    static function getModel($ip, $community, $model = "")
    {
        $test = memory::get($ip);
        if ($test) return $test['Model'];
        $m = @array_values(mSNMP::cache("1.3.6.1.2.1.1.1", $ip, $community))[0];
        if (!$m) {
            self::msg(__METHOD__ . " sw not response $ip", 2);
            return false;
        }
        if($model == "") $model = $m;
        memory::add($ip, 'Model', $model);
        return $model;
    }

    /**
     * Получает все оиды и параметры с базы, пишет в память, возвращает по переданной модели с snmp запроса
     * Если модель не будет найдена - возвращает false.
     *
     * @param $model
     * @return array|bool
     */
    static function getOidLib($model)
    {
        $data = memory::get("OIDLIB");
        $RESP = [];
        if (!$data) {
            //self::msg(__METHOD__." OIDs lib loaded from newstat.SwOids");
            global $DB;
            $sql = dbConn();
            if (!$sql) {
                self::msg(__METHOD__ . " Error connect to DB {$DB['host']}", 5);
                return false;
            }
            $sql->set_charset("utf8");
            $result = $sql->query("SELECT * FROM scripts.OIDS");
            while ($d = $result->fetch_assoc()) {
                $RESP[$d['pattern']]['oids'][$d['type']] = $d['oid'];
                $RESP[$d['pattern']]['model'] = $d['model'];
                $RESP[$d['pattern']]['ports'] = $d['ports'];
                $RESP[$d['pattern']]['rank'] = $d['rank'];
            }
        } else {
            $RESP = $data;
            // self::msg("Данные OID получены по MemCache");
        }
        memory::set("OIDLIB", $RESP);
        foreach ($RESP as $searchM => $d) {
            if (strpos($model, $searchM) !== false) {
               //  self::msg(__METHOD__." Found model: {$d['model']}, data: ".json_encode($d));
                return $d;
            }
        }
        std::msg(__METHOD__ . " Not found oids for this model: $model", 404);
        return false;
    }

    /**
     * Возвращает список поддерживаемых команд с базы по модели.
     * false - в случае, если не найдено
     *
     * @param $model string Модель устройства по sysdescr.
     *
     * @return array|bool - результат обработки
     */
    static function DEPRgetCommandLib($model)
    {
        $data = memory::get("COMMANDLIB");
        $RESP = [];
        if (!$data) {
            //self::msg(__METHOD__." Commands lib loaded from SwModelsCom");
            global $DB;
            $sql = new \mysqli($DB['host'], $DB['login'], $DB['password'], $DB['db']);
            if (!$sql) {
                self::msg(__METHOD__ . " Error connect to DB {$DB['host']}", 5);
                return false;
            }
            $sql->set_charset("utf8");
            $result = $sql->query("SELECT * FROM COMMANDS");
            while ($d = $result->fetch_assoc()) {
                $RESP[$d['pattern']]['commands'][$d['type']] = $d['command'];
                $RESP[$d['pattern']]['model'] = $d['model'];
                $RESP[$d['pattern']]['ports'] = $d['ports'];
                $RESP[$d['pattern']]['rank'] = $d['rank'];
            }
        } else {
            $RESP = $data;
            // self::msg("Данные OID получены по MemCache");
        }
        memory::set("COMMANDLIB", $RESP);
        foreach ($RESP as $searchM => $d) {
            if (strpos($model, $searchM) !== false) {
                //self::msg(__METHOD__." Found model: {$d['model']}");
                return $d;
            }
        }
        return self::msg(__METHOD__ . " Not found commands for this model: $model", 404);
    }


    /**
     * Отправляет HTTP запрос и возвращает ответ.
     * Параметры передаются в POST.
     * HTTP заголовки последнего запроса будут записаны в private self::$headers(получить заголовки можно через метод std::getRequestHeader )
     *
     * @param string $href Адрес страницы
     * @param array $data - массив key=>value, который будет передан через POST
     * @param int $timeout время ожидания ответа
     * @return string|false Тело документа либо false в случае проблемы
     */
    static function sendRequest($href, $data, $timeout = 60)
    {
        $postdata = http_build_query($data);
        $opts = array('http' =>
            array(
                'method' => 'POST',
                'timeout' => $timeout,
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata
            )
        );
        $context = stream_context_create($opts);
        $result =  file_get_contents($href, false, $context);
        self::$headers = $http_response_header;
        return $result;
    }

    static function  getRequestHeader()
    {
        return self::$headers;
    }


    //Логирование
    static function msg($msg, $level = 3)
    {
        global $DEBUG_LOG;
        self::$error = $msg;
        self::$errno = $level;
        $DEBUG_LOG[] = [
            'time' => date("Y-m-d H:i:s"),
            'msg' => $msg,
            'level' => $level
        ];
        $msg = date("Y-m-d H:i:s") . " - ($level) $msg\n";
        if (defined('STDERR')) {
            @fwrite(STDERR, $msg);
        }
        return false;
    }

    //Первый и последний IP по маске
    static function cidrToRange($cidr)
    {
        $range = array();
        $cidr = explode('/', $cidr);
        $range[0] = long2ip((ip2long($cidr[0])) & ((-1 << (32 - (int)$cidr[1]))));
        $range[1] = long2ip((ip2long($cidr[0])) + pow(2, (32 - (int)$cidr[1])) - 1);
        return $range;
    }

    static function Request(& $form)
    {
        if (isset($_REQUEST)) foreach ($_REQUEST as $k => $v) {
            if(is_array($v) || is_object($v)) {
                $form[$k] = $v;
            } else {
                $form[$k] = trim($v);
            }
        }
    }

    //Генерирует JSON ответ
    static function Response($data, $code = 0)
    {
        if ($code > 200 && $code <= 522) {
            self::http_response_code($code);
        }
        std::AllowOrigin();
        if ($code != 204) {
            std::Logs($data, $code);
        }
        global $DEBUG_LOG;
        if ($code == 0) {
            $text =  json_encode(['code' => 0, 'data' => $data, 'debug' => $DEBUG_LOG], JSON_UNESCAPED_UNICODE |JSON_PARTIAL_OUTPUT_ON_ERROR  | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT );
        } else {
           $text = json_encode(['code' => $code, 'errorMessage' => $data, 'debug' => $DEBUG_LOG], JSON_PRETTY_PRINT |JSON_PARTIAL_OUTPUT_ON_ERROR  | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        }
        if(!$text) {
           echo "error: ".json_last_error_msg();
           print_r($data);
        } else {
            echo $text;
        }
         exit;
    }

    static function ResponseTxt($text, $txtOnly=false)
    {
        std::AllowOrigin();

        std::Logs($text, 0);
        if($txtOnly) {
            header('Content-Type: text/json; charset=cp1251', true);
            $text = iconv('utf8','cp1251',strip_tags($text));
        }
        die($text);
    }

    //Генерирует JSON ответ
    static function ResponsePublic($data, $code = 0)
    {
        std::AllowOrigin();
        std::Logs($data, $code);
        if ($code == 0) {
            echo json_encode(['code' => 0, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
        } else {
            $test = json_encode(['code' => $code, 'errorMessage' => $data], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            if (!$test) echo '{"code":3, "errorMessage":"' . $data . '"}'; else echo $test;
        }
        exit;
    }

    //Отправка заголовков
    static function AllowOrigin()
    {
        header('HTTP/1.1 200 OK');
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Headers: Accept, Accept-CH, Accept-Charset, Accept-Datetime, Accept-Encoding, Accept-Ext, Accept-Features, Accept-Language, Accept-Params, Accept-Ranges, Access-Control-Allow-Credentials, Access-Control-Allow-Headers, Access-Control-Allow-Methods, Access-Control-Allow-Origin, Access-Control-Expose-Headers, Access-Control-Max-Age, Access-Control-Request-Headers, Access-Control-Request-Method, Age, Allow, Alternates, Authentication-Info, Authorization, C-Ext, C-Man, C-Opt, C-PEP, C-PEP-Info, CONNECT, Cache-Control, Compliance, Connection, Content-Base, Content-Disposition, Content-Encoding, Content-ID, Content-Language, Content-Length, Content-Location, Content-MD5, Content-Range, Content-Script-Type, Content-Security-Policy, Content-Style-Type, Content-Transfer-Encoding, Content-Type, Content-Version, Cookie, Cost, DAV, DELETE, DNT, DPR, Date, Default-Style, Delta-Base, Depth, Derived-From, Destination, Differential-ID, Digest, ETag, Expect, Expires, Ext, From, GET, GetProfile, HEAD, HTTP-date, Host, IM, If, If-Match, If-Modified-Since, If-None-Match, If-Range, If-Unmodified-Since, Keep-Alive, Label, Last-Event-ID, Last-Modified, Link, Location, Lock-Token, MIME-Version, Man, Max-Forwards, Media-Range, Message-ID, Meter, Negotiate, Non-Compliance, OPTION, OPTIONS, OWS, Opt, Optional, Ordering-Type, Origin, Overwrite, P3P, PEP, PICS-Label, POST, PUT, Pep-Info, Permanent, Position, Pragma, ProfileObject, Protocol, Protocol-Query, Protocol-Request, Proxy-Authenticate, Proxy-Authentication-Info, Proxy-Authorization, Proxy-Features, Proxy-Instruction, Public, RWS, Range, Referer, Refresh, Resolution-Hint, Resolver-Location, Retry-After, Safe, Sec-Websocket-Extensions, Sec-Websocket-Key, Sec-Websocket-Origin, Sec-Websocket-Protocol, Sec-Websocket-Version, Security-Scheme, Server, Set-Cookie, Set-Cookie2, SetProfile, SoapAction, Status, Status-URI, Strict-Transport-Security, SubOK, Subst, Surrogate-Capability, Surrogate-Control, TCN, TE, TRACE, Timeout, Title, Trailer, Transfer-Encoding, UA-Color, UA-Media, UA-Pixels, UA-Resolution, UA-Windowpixels, URI, Upgrade, User-Agent, Variant-Vary, Vary, Version, Via, Viewport-Width, WWW-Authenticate, Want-Digest, Warning, Width, X-Content-Duration, X-Content-Security-Policy, X-Content-Type-Options, X-CustomHeader, X-DNSPrefetch-Control, X-Forwarded-For, X-Forwarded-Port, X-Forwarded-Proto, X-Frame-Options, X-Modified, X-OTHER, X-PING, X-PINGOTHER, X-Powered-By, X-Requested-With");
        header("Connection: close");
        return false;
    }

    static function Logs($data, $code)
    {
        $STD = getGlobalConfigVar('BASE');
        global $DEBUG_LOG;
        $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "127.0.0.1";
        $time = date("Y-m-d");
        $module = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "unknown module";
        $query = isset($_REQUEST) ? $_REQUEST : [];
        unset($query['url']);
        $query = json_encode($query);
        $fileName = $STD['logDir'] . "/" . $time . ".log";
        $content = "============== $module $remote_ip $query ----> respCode: $code =============\n";
        if ($DEBUG_LOG) {
            foreach ($DEBUG_LOG as $l) {
                $content .= "{$l['time']} - ({$l['level']}) {$l['msg']}\n";
            }
            $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
            $content .= "$data\n\n";
            // print_r($STD['logDir']);
            file_put_contents($fileName, $content, FILE_APPEND);
        }
    }

    static function getFiles($dir)
    {
        $files = array();
        if ($dd = opendir($dir)) {
            while ($f = readdir($dd)) {
                if ($f == "." || $f == ".." || (is_dir($dir . "/" . $f))) continue;
                $files[] = $f;
            }
            closedir($dd);
        }
        return $files;
    }

    static function rus2lat($string)
    {
        $converter = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v',
            'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ё' => 'e', 'ж' => 'zh', 'з' => 'z',
            'и' => 'i', 'ї' => 'i', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r',
            'і' => 'i', 'є' => 'є',
            'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V',
            'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
            'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z',
            'И' => 'I', 'Й' => 'Y', 'К' => 'K',
            'І' => 'I', 'Є' => 'E',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R',
            'С' => 'S', 'Т' => 'T', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
            'Ь' => '', 'Ы' => 'Y', 'Ъ' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        ); //Шановний абонент, на рахунку ".$a['agreement']." зал." .$a['balance'].", опл. до $d. Для подальшого користування необхідно поповнити рахунок
        return strtr($string, $converter);
    }

    static function sendSMS($phone, $message = '', $type = '1286')
    {
        try {
            $sms = new \SoapClient('http://109.86.223.228:9966/sms.ASMX?WSDL');
            $sms->SendSMS(array('Number' => "+{$phone}", 'MessageText' => $message, 'Type' => $type, 'Priority' => 8));
        } catch (Exception $err) {
            return false;
        }
        return true;
    }

    static function sendSMSeid($eid, $message = '', $type = '1275', $server = 1250)
    {
        try {
            $sms = new \SoapClient('http://109.86.223.228:9966/sms.ASMX?WSDL');
            $sms->SendSMSById(array('Employee' => "{$eid}", 'MessageText' => $message, 'Type' => $type, 'Priority' => 8, 'Server' => $server));
        } catch (Exception $err) {
            std::msg($err);
            return false;
        }
        return true;
    }
    static  function  checkParam($valid, $values,$name = "") {
        $values = trim($values) ;
        switch ($valid) {
            case 'mac':
                $values = strtoupper(str_replace([':','-',' '],':',$values));
                if(!filter_var($values,FILTER_VALIDATE_MAC)) throw  new \Exception("Isn't no mac-address");
                return $values;
            case 'ip':
                if(!filter_var($values, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    if($name) {
                        throw new \Exception("Неккорентный параметр: $name");
                    }
                    throw new \Exception("Isn't ip-address");
                }
                return $values;
            case 'network':
                $address  = explode("/",$values);
                if(count($address) < 2) throw new \Exception("It is not network");
                if(!filter_var($address[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) throw new \Exception("Isn't ip-address in network");
                if(!preg_match('/[0-9]{1,2}/',$address[1])) throw new \Exception("Incorrect mask");
                return $values;
            case 'port':
                if((int)$values <= 0) throw new \Exception("Порт не может быть меньше, равен нулю");
                return $values;
            case 'number':
                if((int)$values <= 0) throw new \Exception("Число не может быть отрицательным или содержать символы");
                return $values;
        }
        return $values;
    }
    static public function isPicture ($mediapath) {
        if(@is_array(getimagesize($mediapath))){
            $image = true;
        } else {
            $image = false;
        }
        return $image;
    }
    static public function http_response_code($code = NULL) {
        if ($code !== NULL) {
            switch ($code) {
                case 100: $text = 'Continue';
                    break;
                case 101: $text = 'Switching Protocols';
                    break;
                case 200: $text = 'OK';
                    break;
                case 201: $text = 'Created';
                    break;
                case 202: $text = 'Accepted';
                    break;
                case 203: $text = 'Non-Authoritative Information';
                    break;
                case 204: $text = 'No Content';
                    break;
                case 205: $text = 'Reset Content';
                    break;
                case 206: $text = 'Partial Content';
                    break;
                case 300: $text = 'Multiple Choices';
                    break;
                case 301: $text = 'Moved Permanently';
                    break;
                case 302: $text = 'Moved Temporarily';
                    break;
                case 303: $text = 'See Other';
                    break;
                case 304: $text = 'Not Modified';
                    break;
                case 305: $text = 'Use Proxy';
                    break;
                case 400: $text = 'Bad Request';
                    break;
                case 401: $text = 'Unauthorized';
                    break;
                case 402: $text = 'Payment Required';
                    break;
                case 403: $text = 'Forbidden';
                    break;
                case 404: $text = 'Not Found';
                    break;
                case 405: $text = 'Method Not Allowed';
                    break;
                case 406: $text = 'Not Acceptable';
                    break;
                case 407: $text = 'Proxy Authentication Required';
                    break;
                case 408: $text = 'Request Time-out';
                    break;
                case 409: $text = 'Conflict';
                    break;
                case 410: $text = 'Gone';
                    break;
                case 411: $text = 'Length Required';
                    break;
                case 412: $text = 'Precondition Failed';
                    break;
                case 413: $text = 'Request Entity Too Large';
                    break;
                case 414: $text = 'Request-URI Too Large';
                    break;
                case 415: $text = 'Unsupported Media Type';
                    break;
                case 500: $text = 'Internal Server Error';
                    break;
                case 501: $text = 'Not Implemented';
                    break;
                case 502: $text = 'Bad Gateway';
                    break;
                case 503: $text = 'Service Unavailable';
                    break;
                case 504: $text = 'Gateway Time-out';
                    break;
                case 505: $text = 'HTTP Version not supported';
                    break;
                default:
                    exit('Unknown http status code "' . htmlentities($code) . '"');
                    break;
            }
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
            header($protocol . ' ' . $code . ' ' . $text);
            $GLOBALS['http_response_code'] = $code;
        } else {
            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
        }
        return $code;
    }
    static public function prepareParamsForPDO($params, $exclude_keys = []) {
        $prepared = [];
        foreach ($params as $key=>$value) {
            if($value && !in_array($key, $exclude_keys)) $prepared[":{$key}"] = $value;
        }
        return $prepared;
    }
}