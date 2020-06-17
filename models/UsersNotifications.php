<?php
    /**
     * Created by PhpStorm.
     * User: user
     * Date: 12.03.2020
     * Time: 17:08
     */

    namespace models;
    use PDO;


    class UsersNotifications
    {
        private $connect;
        private $tableName = "users_notifications";
        private $chatId;
        private $logger;

        private $type_linz;
        public $phase_settings;
        private $next_message;

        const PHASE_START = 1;
        const PHASE_SET_TYPE = 2;
        const PHASE_WEEK_NOTIFICATION = 3;
        const PHASE_TODAY_NOTIFICATION = 4;
        const PHASE_2_DAYS_NOTIFICATION = 5;

        const TYPE_MONTH = 1;
        const TYPE_3_MONTHS = 3;

        public function __construct($chatId = null){
            $this->connect = (new Database())->getConnection();
            $this->chatId = $chatId;
            $this->logger = new Logs();
        }

        private function checkUser() {
            $query = "  SELECT
                          $this->tableName.id   
                        FROM $this->tableName 
                        WHERE 
                           $this->tableName.chat_id = $this->chatId";

            $data = $this->connect->prepare($query);

            $data->execute();

            $rowsCount = $data->rowCount();

            return ($rowsCount > 0) ? true :false;
        }

        public function addUser() {
            if (self::checkUser()) {
                self::removeUser();
            }
            $query = "INSERT INTO $this->tableName ($this->tableName.chat_id, $this->tableName.phase_settings)
                  VALUES ($this->chatId, " . self::PHASE_START . ")";

            $result = $this->connect->prepare($query);

            $result->execute();

            return self::checkError($result) ? false : true;
        }

        private function removeUser() {
            $query = "  DELETE
                        FROM $this->tableName 
                        WHERE 
                           $this->tableName.chat_id = $this->chatId";

            $result = $this->connect->prepare($query);

            $result->execute();

            return self::checkError($result) ? false : true;
        }

        public function setTypeLinz($type) {
            $query = "UPDATE $this->tableName SET $this->tableName.type_linz = $type, $this->tableName.phase_settings = " . self::PHASE_SET_TYPE . "
                  WHERE $this->tableName.chat_id = $this->chatId AND $this->tableName.phase_settings = " . self::PHASE_START;

            $result = $this->connect->prepare($query);

            $result->execute();

            return self::checkError($result) ? false : true;
        }

        public function startPeriod($startDate) {
            $notificationDate = self::createNotificationDate($startDate, 1);
            $startDate = (new \DateTime($startDate))->format('Y-m-d');

            $query = "UPDATE $this->tableName SET $this->tableName.start_period = '$startDate', $this->tableName.phase_settings = " . self::PHASE_WEEK_NOTIFICATION . ",
                  $this->tableName.next_message = '$notificationDate'
                  WHERE $this->tableName.chat_id = $this->chatId 
                  AND ($this->tableName.phase_settings = " . self::PHASE_SET_TYPE . " OR 
                  $this->tableName.phase_settings = " . self::PHASE_TODAY_NOTIFICATION . " OR
                  $this->tableName.phase_settings = " . self::PHASE_2_DAYS_NOTIFICATION . ")";

            $result = $this->connect->prepare($query);

            $result->execute();



            return self::checkError($result) ? false : true;
        }

        public function updateMessageDate($phase = self::PHASE_TODAY_NOTIFICATION) {
            $notificationDate = self::createNotificationDate();

            $query = "UPDATE $this->tableName SET $this->tableName.phase_settings = $phase, $this->tableName.next_message = '$notificationDate'
                  WHERE $this->tableName.chat_id = $this->chatId AND ($this->tableName.phase_settings = " . ($phase - 1) . " OR $this->tableName.phase_settings = $phase )";

            $result = $this->connect->prepare($query);

            $result->execute();

            return self::checkError($result) ? false : true;
        }

        public function getNotification($phase) {
            $date = (new \DateTime('now'))->format('Y-m-d');
            $query = "  SELECT
                          $this->tableName.chat_id   
                        FROM $this->tableName 
                        WHERE $this->tableName.phase_settings = $phase AND $this->tableName.next_message = '$date'";

            $data = $this->connect->prepare($query);

            $data->execute();

            $rowsCount = $data->rowCount();

            if ($rowsCount > 0 && !$this->checkError($data)) {
                $notificationArray = array();

                while ($row = $data->fetch(PDO::FETCH_ASSOC)) {
                    array_push($notificationArray, $row);
                }
            }
            return $notificationArray ?? null;
        }

        /**
         * @param $result \PDOStatement
         * @param $chatId integer
         * @return bool
         */
        private function checkError($result) {
            if ($result->errorCode() == 0) {
                return false;
            } else {
                $this->logger->writeLog("Query : " . PHP_EOL . $result->queryString . PHP_EOL . "Error :" .
                    PHP_EOL . implode(PHP_EOL,$result->errorInfo()), $this->chatId);
                return true;
            }
        }

        public function getDataFromDB() {
            $query = "  SELECT
                          *  
                        FROM $this->tableName 
                        WHERE 
                           $this->tableName.chat_id = $this->chatId";

            $data = $this->connect->prepare($query);

            $data->execute();

            $rowsCount = $data->rowCount();

            if  ($rowsCount > 0) {
                $row = $data->fetch(PDO::FETCH_ASSOC);
                extract($row,EXTR_OVERWRITE);

                $this->phase_settings = $phase_settings ?? null;
                $this->type_linz = $type_linz ?? null;
                $this->next_message = $next_message ?? null;
            }
        }

        private function createNotificationDate($startDate = null, $new = null) {
            self::getDataFromDB();
            if ($startDate == null) $startDate = $this->next_message;
            $notificationDate = new \DateTime($startDate);
            if ($new) $this->phase_settings = self::PHASE_SET_TYPE;
            switch ($this->phase_settings + 1) {
                case self::PHASE_WEEK_NOTIFICATION :
                    if ($this->type_linz == self::TYPE_MONTH)
                        $notificationDate->modify('+23 days');
                    else
                        $notificationDate->modify('+83 days');
                    break;
                case self::PHASE_TODAY_NOTIFICATION :
                    $notificationDate->modify('+7 days');
                    break;
                default :
                    $notificationDate->modify('+2 days');
                    break;
            }

            return $notificationDate->format('Y-m-d');
        }

        public function getType(){
            return $this->type_linz;
        }
    }