<?php

/*  Функция отправляет сообщение по событию сервера  */
function sendMsg($id, $mess, $flag_die) {
    echo "id: $id" . PHP_EOL;
    echo "data: $mess" . PHP_EOL;
    echo PHP_EOL;
//    ob_flush();
    flush();

    if($flag_die){
        die();
    }
}


