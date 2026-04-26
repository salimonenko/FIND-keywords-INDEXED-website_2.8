<?php

// 1. Задаваемые параметры/функции
require_once __DIR__ . '/parametrs.php'; // Здесь параметрам даются целевые значения
require_once __DIR__ . '/sendMsg.php'; // Вывод результатов событий сервера
/*if (!function_exists('http_response_code')){
    require_once __DIR__ . '/../PHP_5.3/http_response_code.php';
}*/


if(file_exists($DO_working_flag_FILE)){
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');

    $mess = '<p class="error_mes">Возможно... процесс индексирования уже запущен. Для запуска индексирования снова нужно вначале ОСТАНОВИТЬ текущий процесс (для этого нажмите кнопку "Остановить индексирование")...</p>';
    sendMsg(time(), $mess, false);
    die();
}

require_once __DIR__ . '/file_INDEXER.php';
