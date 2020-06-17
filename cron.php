<?php
    /**
     * Created by PhpStorm.
     * User: user
     * Date: 12.03.2020
     * Time: 21:11
     */
    namespace bot;
    include_once 'include.php';

    $telegramService = new TelegramService();

    $telegramService->commandNotification();