<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 28.02.2020
 * Time: 16:33
 */
namespace models;
use PDO;
use PDOException;

class Database
{
    private $host = "skazki03.mysql.tools";
    private $db_name = "skazki03_botlinz";
    private $username = "skazki03_botlinz";
    private $password = "69OkI(t^e8";
    public $db;

    public function getConnection() {
        unset($this->db);

        try {
            $this->db = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->db->exec("set names utf8");
        } catch(PDOException $exception){
           echo $exception->getMessage();
        }

        return $this->db;
    }
}