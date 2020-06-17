<?php


namespace bot;

use models\Logs;
use models\UsersNotifications;
use TelegramBot\Api\BotApi;
use Exception;

class TelegramService
{
    const COMMAND_START = '/start';

    const BUTTON_TYPE_MONTH = 'На місяць';

    const BUTTON_TYPE_3_MONTHS = 'На 3 місяці';

    const BUTTON_START_NOW = 'Починаю сьогодні';

    const BUTTON_RESTART_NOW = 'Заміняю лінзи сьогодні';

    const BUTTON_REMIND_2_DAYS = 'Нагадати через 2 дні';

    private $chatId;

    private $bot;

    private $logger;

    public function __construct()
    {
        $this->bot = new BotApi('984921797:AAGuTDBdR0bah9dimwq5_2sdyv3EKeUJcLQ');
        $this->logger = new Logs();
    }

    public function getCommand(array $receivedData)
    {
        try {
            $message = $receivedData['message']['text'] ?? null;
            $this->chatId = $receivedData['message']['chat']['id'];

            switch ($message) {
                case self::COMMAND_START :
                    $this->commandStart();
                    break;

                case self::BUTTON_TYPE_MONTH :
                    $this->commandSetType(UsersNotifications::TYPE_MONTH);
                    break;

                case self::BUTTON_TYPE_3_MONTHS :
                    $this->commandSetType(UsersNotifications::TYPE_3_MONTHS);
                    break;

                case self::BUTTON_START_NOW:
                    $this->commandStartPeriod();
                    break;

                case self::BUTTON_RESTART_NOW:
                    $this->commandStartPeriod();
                    break;

                case self::BUTTON_REMIND_2_DAYS:
                    $this->commandRemind2Days();
                    break;

                default :
                    $model = new UsersNotifications($this->chatId);
                    $model->getDataFromDB();
                    if ($model->phase_settings == UsersNotifications::PHASE_SET_TYPE && strlen($message) == 10 && strtotime($message)) {
                        $this->commandStartPeriod($message);
                    } else {
                        $this->commandBadResponse();
                    }
            }
        } catch (Exception $e) {
            $this->logger->writeLog($e->getMessage(),$this->chatId);
        }
    }

    public function commandStart() {
        $model = new UsersNotifications($this->chatId);
        $model->addUser();
        try {
            $this->bot->sendMessage(
                $this->chatId,
                "Привіт, я буду нагадувати тобі про вчасну заміну лінз");

            $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup(array(array(self::BUTTON_TYPE_MONTH, self::BUTTON_TYPE_3_MONTHS)), true, true);

            $this->bot->sendMessage(
                $this->chatId,
                "Який у тебя тип лінз?",
                null,
                false,
                null,
                $keyboard);
        } catch (Exception $e) {
            $this->logger->writeLog($e->getMessage(),$this->chatId);
        }
    }

    public function commandSetType($type) {
        $model = new UsersNotifications($this->chatId);
        try {
            if ($model->setTypeLinz($type)) {

                $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup(array(array(self::BUTTON_START_NOW)), true, true);

                $this->bot->sendMessage(
                    $this->chatId,
                    "Коли ти почав носити свої лінзи? (Натисніть на кнопку \"Починаю сьогодні\" або введіть дату у форматі: день.місяць.рік - 20.02.2020)",
                    null,
                    false,
                    null,
                    $keyboard);
            }
        } catch (Exception $e) {
            $this->logger->writeLog($e->getMessage(),$this->chatId);
        }
    }

    public function commandStartPeriod($date = 'now') {
        $model = new UsersNotifications($this->chatId);
        try {
            if ($model->startPeriod($date)) {
                $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardRemove();

                $this->bot->sendMessage(
                    $this->chatId,
                    "Супер! Я тобі нагадаю за тиждень до заміни, щоб в тебе був час придбати нові. Та у день, коли тобі необхідно їх замінити.",
                    null,
                    false,
                    null,
                    $keyboard);
            }
        } catch (Exception $e) {
            $this->logger->writeLog($e->getMessage(),$this->chatId);
        }
    }

    public function commandNotification() {
        try {

            $model = new UsersNotifications();
            $phase = UsersNotifications::PHASE_WEEK_NOTIFICATION;
            $list = $model->getNotification($phase);
            foreach ($list as $chatId) {
                $userData = new UsersNotifications($chatId['chat_id']);
                $userData->getDataFromDB();
                $link = ($userData->getType() == UsersNotifications::TYPE_MONTH) ?  "https://panokulist.com/kontaktni-linzy/1-misiats"
                    : "https://panokulist.com/kontaktni-linzy/3-misiatsi";
                $this->bot->sendMessage(
                    $chatId['chat_id'],
                    "Привіт! Через тиждень тобі необхідно замінити лінзи. Звертайся у свою оптику чи замовляй онлайн - $link");
                $userData->updateMessageDate();
            }

            $phase = UsersNotifications::PHASE_TODAY_NOTIFICATION;
            $list = $model->getNotification($phase);
            foreach ($list as $chatId) {
                $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup(array(array(self::BUTTON_RESTART_NOW, self::BUTTON_REMIND_2_DAYS)), true, true);
                $this->bot->sendMessage(
                    $chatId['chat_id'],
                    "Час замінити лінзи на нові.",
                    null,
                    false,
                    null,
                    $keyboard);
            }

            $phase = UsersNotifications::PHASE_2_DAYS_NOTIFICATION;
            $list = $model->getNotification($phase);
            foreach ($list as $chatId) {
                $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup(array(array(self::BUTTON_RESTART_NOW, self::BUTTON_REMIND_2_DAYS)), true, true);
                $this->bot->sendMessage(
                    $chatId['chat_id'],
                    "Привіт, економний ;) Час замінити лінзи.",
                    null,
                    false,
                    null,
                    $keyboard);
            }
        } catch (Exception $e) {
            $this->logger->writeLog($e->getMessage(),$this->chatId);
        }
    }

    public function commandRemind2Days() {
        $model = new UsersNotifications($this->chatId);
        $model->updateMessageDate(UsersNotifications::PHASE_2_DAYS_NOTIFICATION);
    }

    public function commandBadResponse() {
        try {
            $this->bot->sendMessage(
                $this->chatId,
                "Зробіть свій вибір по кнопкам, або ж правильно введіть дату.");
        } catch (Exception $e) {
            $this->logger->writeLog($e->getMessage(),$this->chatId);
        }
    }
}