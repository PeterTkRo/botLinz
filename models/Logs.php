<?php
    /**
     * Created by PhpStorm.
     * User: user
     * Date: 11.03.2020
     * Time: 12:53
     */

    namespace models;
    use PDO;


    class Logs
    {
        /**
         * @var $connect PDO()
         */
        private $connect;
        private $tableName = "logs";

        public function __construct(){
            $this->connect = (new Database())->getConnection();
        }
        public function writeLog($message, $chatId){
            $query = "INSERT INTO $this->tableName ($this->tableName.message, $this->tableName.chat_id)
                  VALUES (\"$message\", $chatId)";

            $result = $this->connect->prepare($query);

            $result->execute();

            return true;
        }
    }