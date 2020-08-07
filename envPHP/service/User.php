<?php


namespace envPHP\service;


use Ramsey\Uuid\Uuid;

class User
{
    protected $sql;
    public function __construct()
    {
        $this->sql = dbConn();
    }

    public function generateToken($userId) {
        if(!$userId) {
            throw new \InvalidArgumentException("Incorrect user ID");
        }
       $uuid = Uuid::uuid4();
       if(!$this->sql->query("INSERT INTO emplo_tokens (token, employee, created_at, expired_at) VALUES ('{$uuid->toString()}', '$userId', NOW(), NOW() + INTERVAL 14 DAY)")) {
           throw new \Exception("SQL ERR: " . $this->sql->error);
       };
       return $uuid->toString();
    }
    public function auth($login, $password) {
        if($id = $this->sql->query("SELECT * FROM employees WHERE login = '$login' and BINARY password = '$password'")->fetch_assoc()['id']) {
            return $id;
        }
        return false;
    }

    /**
     * @param $tokenUUID
     * @return int -2 - user with token not found, -1 - token expired, int > 0 - employee ID
     */
    public function validateToken($tokenUUID) {
        if($info = $this->sql->query("SELECT employee, if(expired_at > NOW(), 1 , 0) expired  FROM emplo_tokens WHERE token = '{$tokenUUID}'")->fetch_assoc()) {
            if($info['expired']) {
                return $info['employee'];
            } else {
                return -1;
            }
        }
        return -2;
    }
    public function getUserByLogin($login) {
        $id = $this->sql->query("SELECT id FROM employees WHERE login = '$login'")->fetch_assoc()['id'];
        return $this->getUser($id);
    }
    public function getUser($userId) {
        return $this->sql->query("SELECT e.id, e.name, e.phone, e.skype, e.mail email, e.login, if(IFNULL(e.display, 0) = 0, false, true) display, e.telegram_id, p.position, p.rank access_level
                FROM employees e 
                JOIN emplo_positions p on p.id = e.position
                WHERE e.id = '$userId'
                ;")->fetch_assoc();
    }
}