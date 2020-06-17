<?php
    namespace bot;
    include_once 'include.php';

    $telegramService = new TelegramService();

    $postData = json_decode(file_get_contents("php://input"), true);

    $telegramService->getCommand($postData);
