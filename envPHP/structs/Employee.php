<?php
/**
 * Created by PhpStorm.
 * User: Meklis
 * Date: 10.05.2019
 * Time: 16:46
 */

namespace envPHP\structs;


class Employee
{
  protected $id;
  protected $name;
  protected $login;
  protected $phone;
  protected $mail;
  protected $position;
  protected $rank;
  function fillById($employee_id)
  {
      $data = dbConn()->query("SELECT 
                e.id, 
                e.login,
                e.mail,
                e.`name`,
                e.phone,
                p.position,
                p.rank
                FROM employees e 
                JOIN emplo_positions p on p.id = e.position
                WHERE e.id = $employee_id
      ")->fetch_assoc();
      if(!$data['id']) {
          throw new \Exception("User not found. ID={$employee_id}");
      }
      foreach ($data as $key=>$val) {
          $this->{$key} = $val;
      }

      return $this;
  }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return Employee
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     * @return Employee
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLogin()
    {
        return $this->login;
    }

    /**
     * @param mixed $login
     * @return Employee
     */
    public function setLogin($login)
    {
        $this->login = $login;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @param mixed $phone
     * @return Employee
     */
    public function setPhone($phone)
    {
        $this->phone = $phone;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMail()
    {
        return $this->mail;
    }

    /**
     * @param mixed $mail
     * @return Employee
     */
    public function setMail($mail)
    {
        $this->mail = $mail;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param mixed $position
     * @return Employee
     */
    public function setPosition($position)
    {
        $this->position = $position;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRank()
    {
        return $this->rank;
    }

    /**
     * @param mixed $rank
     * @return Employee
     */
    public function setRank($rank)
    {
        $this->rank = $rank;
        return $this;
    }



}