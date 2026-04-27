<?php
/* Программа индексирования содержимого файлов сайта. Потом в индексированном содержимом можно очень быстро сделать НЕчеткий поиск даже при (очень) большом числе файлов.
Работает с использованием (пока) двух кодировок: Windows-1251 и utf-8. В этих кодировках могут содержаться как имена файлов сайта, так и их содержимое.
ВАЖНО: В этой программе почти каждому метафонному символу соответствует ОТДЕЛЬНЫЙ каталог с таким же именем. Последние 2 символа метафона - начало строки в текстовом файле (1.txt)
*/
mb_internal_encoding("utf-8");
$internal_enc = mb_internal_encoding();
mb_regex_encoding($internal_enc);

/* Список недопустимых имен каталогов в Windows:
CON, PRN, AUX, NUL, COM0, COM1, COM2, COM3, COM4, COM5, COM6, COM7, COM8, COM9, LPT0, LPT1, LPT2, LPT3, LPT4, LPT5, LPT6, LPT7, LPT8, LPT9.
CON: — console (input and output)
AUX: — an auxiliary device. In CP/M 1 and 2, PIP used PUN: (paper tape punch) and RDR: (paper tape reader) instead of AUX:
LST: — list output device, usually the printer
PRN: — as LST:, but lines were numbered, tabs expanded and form feeds added every 60 lines
NUL: — null device, akin to /dev/null
EOF: — input device that produced end-of-file characters, ASCII 0x1A
INP: — custom input device, by default the same as EOF:
OUT: — custom output device, by default the same as NUL:
*/

// <script>manage_message(document.currentScript.previousSibling)</script>;


$t0 = microtime(true);


// 0. Устанавливаем значения опасных переменных по умолчанию:
$begins = array(); $ends = array(); $begin = ''; $end = ''; $total_size = 0; // Для начала
//$min_WORD_len = 0; $metaphone_len = 0; $path_DIR_name = ''; $predlogi_PATH = '';
//$path_FILE_name_STRING = '';  $DO_working_flag_FILE = ''; $JS_manage_mes = ''; $reg_keywords = ''; // Они задаются в файле parametrs.php


// 1. Задаваемые параметры/функции (ПРИ ИЗМЕНЕНИИ ЭТИХ ПАРАМЕТРОВ ПОТРЕБУЕТСЯ ИНДЕКСАЦИЯ ВСЕХ ФАЙЛОВ сайта ЗАНОВО!):
require_once __DIR__ . '/parametrs.php';
require_once __DIR__ . '/common_functions.php';


//*************   2. Проверяем, не было ли POST-запросов   *************************
if(isset($_POST['Notepad_PP']) && $_POST['Notepad_PP'] === 'Notepad_PP'){
    header('Content-type: text/html; charset=utf-8');

    if(isset($_POST['n'])){
        if(!preg_match('|^[\d]{1,10}$|', $_POST['n'])){ // Если есть что-то, помимо цифр
            die('Неверный запрос браузера: число n может содержать только цифры, не более 10 символов<br/>');
        }else{
            $n = $_POST['n'];
        }
    }else{
        $n = 1;
    }
    if(isset($_POST['c'])){
        if(!preg_match('|^[\d]{1,10}$|', $_POST['c'])){ // Если есть что-то, помимо цифр
            die('Неверный запрос браузера: число c может содержать только цифры, не более 10 символов<br/>');
        }else{
            $n = $_POST['c'];
            $c = 0;
        }
    }else{
        $c = 0;
    }
// Значит, запрос на открытие файла files.txt
$file_to_open = PATH_FILE_NAMES_ALL_FILES;

start_NotepadPP_working1($file_to_open, $n, $c);

die();
}
// Если был запрос на открытие файла-лога ошибок
if(isset($_POST['Notepad_PP']) && $_POST['Notepad_PP'] === 'Notepad_PP_error_log'){
    header('Content-type: text/html; charset=utf-8');

    if(isset($_POST['n'])){
        if(!preg_match('|^[\d]{1,10}$|', $_POST['n'])){ // Если есть что-то, помимо цифр
            die('Неверный запрос браузера: число n может содержать только цифры, не более 10 символов<br/>');
        }else{
            $n = $_POST['n'];
        }
    }else{
        $n = 1;
    }
    if(isset($_POST['c'])){
        if(!preg_match('|^[\d]{1,10}$|', $_POST['c'])){ // Если есть что-то, помимо цифр
            die('Неверный запрос браузера: число c может содержать только цифры, не более 10 символов<br/>');
        }else{
            $c = $_POST['c'];
        }
    }else{
        $c = 0;
    }

    $file_to_open = PATH_FILE_NAMES_ERROR;

    if(!file_exists(PATH_FILE_NAMES_ERROR)){
        die('Похоже, файл-лог ошибок индексирования еще не создавался (значит, ошибок не было) или он был удален.');
    }

    start_NotepadPP_working1($file_to_open, $n, $c);

die();
}
// Запрос на определение номера строки в файле files.txt
if(isset($_POST['ask_string_number']) && $_POST['ask_string_number'] === 'null'){
    header('Content-type: text/html; charset=utf-8');

    $str_num = file_get_contents($path_FILE_name_STRING);

    if($str_num === false){
        echo -1;
    }elseif(trim($str_num) === ''){
        echo 0;
    }else{
        echo trim($str_num);
    }

die();
}
// запрос на сохранение номера строки, начиная с которой будут просматриваться строки в файле files.txt для последующей индексации
if(isset($_POST['save_string_number'])){
    header('Content-type: text/html; charset=utf-8');

    if(!preg_match('|^[\d]{1,10}$|', $_POST['save_string_number'])){ // Если есть что-то, кроме цифр
        die('Неверный номер строки. Он может содержать только положительное целое число, не более 10 символов<br/>');
    }elseif($_POST['save_string_number'] < 1){
        die('Неверный номер строки. Он может быть только положительным, не более 10 символов<br/>');
    }else{
        $string_number = $_POST['save_string_number'];
        if(file_put_contents($path_FILE_name_STRING, $string_number)){
          echo '<p class="info_mes">Номер строки установлен. Для индексирования файлов сайта, начиная с номера строки, равного '. $string_number. ', обновите эту страницу...</p>';
        }else{
            $mess = 'Не получилось записать номер строки '. $string_number .' в файл '. $path_FILE_name_STRING;
            file_put_contents(PATH_FILE_NAMES_ERROR, $mess. ' '. date("d.m.Y - H:m:s"). PHP_EOL , FILE_APPEND);
            echo '<p class="error_mes">'. $mess . '</p>';
        }

    }
die();
}
// Запрос на поиск и показ номеров строк из файла files.txt, которые (точнее, файлы с содержащимися там именами) еще не были проиндексированы
if(isset($_POST['ask_string_numbers_NOT_indexed']) && $_POST['ask_string_numbers_NOT_indexed'] === 'null'){
    header('Content-type: text/html; charset=utf-8');

    if(!file_exists(PATH_FILE_NAME_INDEXED_SUCCESS)){
        file_put_contents(PATH_FILE_NAME_INDEXED_SUCCESS, '');
    }

    $numbers_Arr = explode(PHP_EOL, file_get_contents(PATH_FILE_NAME_INDEXED_SUCCESS));
    $numbers_Arr = array_values(array_unique($numbers_Arr));

    file_put_contents(PATH_FILE_NAME_INDEXED_SUCCESS, implode(PHP_EOL, $numbers_Arr)); // Попутно, пересохраняем в файл только уникальные номера строк

    $ALL_str_Arr = explode(PHP_EOL, file_get_contents(PATH_FILE_NAMES_ALL_FILES));

    $ALL_str_numbers_Arr = array();
    $max_size_to_SHOW = 10000;

    $size = min($max_size_to_SHOW, sizeof($ALL_str_Arr)); // Показываем только первые 10000 номеров

    if($size === $max_size_to_SHOW){
        echo '<p class="info_mes">(Показаны только '.$max_size_to_SHOW. ' первых номеров строк)</p>';
    }

    for ($i=1; $i < $size; $i++){ // Создаем массив номеров строк, от 1 до максимального (равного числы строк в файле files.txt)
        $ALL_str_numbers_Arr[$i] = $i;
    }

    $numbers_Arr = array_diff($ALL_str_numbers_Arr, $numbers_Arr);

    if(sizeof($numbers_Arr) > 0){
        echo '<h2>Вот номера строк из файла files.txt. В этих строках содержатся имена файлов, которые еще НЕ были поиндексированы:</h2>';
        echo implode(' ', $numbers_Arr);
    }else{
        echo '<h2>Все строки (с именами файлов сайта) успешно проиндексированы. Непроиндексированных файлов нет (среди разрешенных и/или не запрещенных).</h2>';
    }

die();
}


// Запрос на запуск/остановку индексирования
if(isset($_REQUEST['DO_working'])){ // 1. Запуск индексирования ФАЙЛОВ сайта (исходя из перечня их имен в файле files.txt)
    if(isset($_GET['DO_working']) && $_GET['DO_working'] === 'true'){ // Вызывается при запуске событий сервера из file_INDEXER_events.php
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');

        if(!isset($_GET['last_managed_string']) || !preg_match('/^\d{1,10}$/', $_GET['last_managed_string'])){
            $mess = '<p class="error_mes">Отсутствует или неверное значение номера строки: допустимо только положительное целое число, не более 10 цифр.</p>';
            sendMsg(time(), $mess, false);
            die();
        }

        if(!isset($_GET['flag_time_matter']) || !preg_match('/^\d$/', $_GET['flag_time_matter'])){
            $mess = '<p class="error_mes">Ошибка: отсутствует или неверное значение параметра флага актуальности времени создания файла files.txt: допустимы только 0 или 1.</p>';
            sendMsg(time(), $mess, true);
            die();
        }

        if(file_exists($DO_working_flag_FILE)){
            $mess = '<p class="error_mes">Возможно, процесс индексирования уже запущен. Для запуска индексирования снова нужно вначале ОСТАНОВИТЬ текущий процесс (для этого нажмите кнопку "Остановить индексирование")...</p>';
            sendMsg(time(), $mess, false);
            die();
        }

        $number = $_GET['last_managed_string'];
        $flag_time_matter = $_GET['flag_time_matter'];

        file_put_contents($path_FILE_name_STRING, $number); // Сохраняем номер строки, начиная с которого пойдет процесс индексирования

    file_put_contents($DO_working_flag_FILE, ''); // Создаем файл-флаг. Если он присутствует, то итерации цикла перебора файлов (содержащихся в файле files.txt) будут продолжаться. Если нет - то цикл будет остановлен
// Определяем кодировку файла с перечнем файлов сайта и получаем массив, состоящий из имен этих файлов
    $rez_Arr = get_files_Arr($enc_Arr);
    $ALL_files_Arr = $rez_Arr[0];
    $ENC_FILE_names_all_files = $rez_Arr[1];
    $max_UNIX_saved = $rez_Arr[2]; // Метка времени, запасенная в файле-перечне files.txt

    if($ALL_files_Arr === -1){ // В случае ошибки
        sendMsg(time(), $ENC_FILE_names_all_files, true);
        die(); // Для наглядности останова
    }

    if($flag_time_matter === '0'){
        $max_UNIX_saved = 0; // Вне зависимости от меток времени, будет все (начиная с указанной строки в файле files.txt) индексироваться заново
    }

// запускаем саму процедуру индексирования
    indexer($predlogi_PATH, $min_WORD_len, $internal_enc, $path_FILE_name_STRING, $ALL_files_Arr, $max_UNIX_saved, $DO_working_flag_FILE, $ENC_FILE_names_all_files, $enc_Arr, $total_size, $begins, $ends, $path_DIR_name, $path_common_index_name, $metaphone_len, $path_FILE_max_UNIX, $str_UNIX_begin, $str_UNIX_end, $JS_manage_mes, $min_index_FILE_len);

        @unlink($DO_working_flag_FILE);

// 8. Контроль общего времени выполнения
$t1 = microtime(true);

    $mess = '<br/><br/>Затрачено времени: '. ($t1 - $t0). ' секунд.';

    $mess .= '<script>server_Listening(false, "123", "<b>Индексация остановлена.</b>", "file_INDEXER_events.php", "rezults", "rezults", "rezults", true);</script>';
        sendMsg(time(), $mess, true);

    }

    if($_POST['DO_working'] === 'false'){ // 2. Останов
        header('Content-type: text/html; charset=utf-8');

        if(!file_exists($DO_working_flag_FILE)){

            echo '<p class="info_mes">Флаговый файл уже был удален ранее. Его больше нет. Индексирование уже было остановлено.</p>';
            die();
        }

        @unlink($DO_working_flag_FILE);

        if(!file_exists($DO_working_flag_FILE)){
            echo 'false'; // Сообщаем клиенту, что флаговый файл удален и индексирование остановлено
        }else{ // Флаговый файл почему-то не получилось удалить
            $mess = 'Error_unlink_flag_FILE: Флаговый файл '. $DO_working_flag_FILE. ' почему-то не получилось удалить';
            file_put_contents(PATH_FILE_NAMES_ERROR, $mess. ' '. date("d.m.Y - H:m:s"). PHP_EOL , FILE_APPEND);
            echo 'Error_unlink_flag_FILE';
        }
    }else{
        // ...
    }

die();
}


// Запрос на сортировку файла-словаря ru.dic
if(isset($_POST['sort_ru_dic']) && $_POST['sort_ru_dic'] === 'null'){
    header('Content-type: text/html; charset=utf-8');

    define('flag_perfom_working', 1);
    require __DIR__ . '/dic_sorting.php';

    DIC_sort($ru_dic_FILE_NAME_saved, $ru_dic_FILE_NAME, $path_DIR_name, $path_common_index_name, $min_WORD_len, $metaphone_len, $DO_working_flag_FILE, $ru_dic_indexing_info_file, $min_index_FILE_len);

die();
}

// Запрос на удаление каталога с индексными файлами и всеми подкаталогами
if(isset($_POST['del_endexed_FILES_DIRS']) && $_POST['del_endexed_FILES_DIRS'] === 'null'){
    header('Content-type: text/html; charset=utf-8');

    if(!is_dir($path_DIR_name)){
        die('Каталог '. $path_DIR_name. ' не существует.<br/>');
    }

file_put_contents(PATH_FILE_NAME_INDEXED_SUCCESS, ''); // Создаем пустой файл-лог с индексами проиндексированных файлов (старый будет удален)

file_put_contents($DO_working_flag_FILE, ''); // Создаем файл-флаг. Если он присутствует, то итерации рекурсии удаления вложенных каталогов и файлов будут продолжаться

    rrmdir($path_DIR_name, $DO_working_flag_FILE);
// Проверяем, полностью ли удален каталог с индексными файлами
    if(is_dir($path_DIR_name)){
        echo 'Каталог с индексными файлами<br/> <b>'.$path_DIR_name. '</b><br/> НЕ удален полностью, там еще остались каталоги/файлы. Чтобы удалить его полностью, нужно повторить операцию удаления.<br/>';
    }else{
        echo 'Каталог с индексными файлами '. $path_DIR_name. ' полностью удален.<br/> Для реализации поиска среди индексированных файлов следует заново провести сортировку словаря (2), а также индексирование ФАЙЛОВ сайта (3).<br/>';
    }

die();
}


header('Content-type: text/html; charset=utf-8');
//***************************************************************************************
?>

<script src="file_INDEXER.js"
        data-path_file_names_error = "<?php echo basename(PATH_FILE_NAMES_ERROR); ?>"
        data-path_file_names_all_files = "<?php echo basename(PATH_FILE_NAMES_ALL_FILES); ?>">
</script>

<?php
/* 3. Проверяем наличие файла files.txt и, если его нет, выдаем сообщение и кнопку для индексации ИМЕН файлов сайта(за исключением запрещенных)
      Файл files.txt формируется при помощи file_FINDER.php    */
if(!file_exists(PATH_FILE_NAMES_ALL_FILES)){
echo '<input src="imgs/indexing-files.png" style="background-image: none; vertical-align: middle; margin-left: 15px; width: 41px;" onclick="file_FINDER(\'ALL\')" class="buttons_REDACTOR" title="Запустить индексирование ИМЕН всех файлов сайта" alt="Индексировать" type="image"><br/>';
    die('Ошибка: не найден файл с перечнем всех файлов сайта (за исключением запрещенных) <b>'. PATH_FILE_NAMES_ALL_FILES. '</b>. Возможно, требуется сделать индексирование ИМЕН всех файлов сайта. Для этого - нажмите на кнопку.<br/>');
}


// 4. Определяем кодировку файла с перечнем файлов сайта и получаем массив, состоящий из имен этих файлов
$rez_Arr = get_files_Arr($enc_Arr);
$ALL_files_Arr = $rez_Arr[0];
$ENC_FILE_names_all_files = $rez_Arr[1];

    if($ALL_files_Arr === -1){ // В случае ошибки
        sendMsg(time(), $ENC_FILE_names_all_files, true);
        die(); // Для наглядности
    }

// *********************************************************************************
// 5. Выдаем HTML-содержимое (в частности, панель управления)
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <title>ПОИСК, Индексирование имен файлов сайта и их содержимого для реализации быстрого нечеткого поиска по искомым словам. Поиск по словам.</title>

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes"/>
<style type="text/css">

* { font-size: 13px; box-sizing: border-box; font-family: Arial}
/*  Перенос слов  */
.hyphens{ -webkit-hyphens: auto; -moz-hyphens: auto; ms-hyphens: auto; hyphens: auto; word-wrap: break-word;}

#panel { display: table-cell; position: relative; max-width: 500px; vertical-align: top; margin-right: 15px; }
#rezults { display: table-cell; padding-left: 10px}

#tmpID { z-index: 1; width: 500px; margin-left: 0px; position: relative; background-color: #FFF3D1; text-align: left; border: medium solid;  }

.buttons_REDACTOR {position: relative; display: inline-block; width: auto; padding: 0px 1px 1px 0px; height: auto; vertical-align: middle; text-align: left; border-width: 1px; border-style: solid;
    -moz-border-top-colors: none; -moz-border-right-colors: none; -moz-border-bottom-colors: none; -moz-border-left-colors: none;
    border-image: none; background-color: #F3F3F3; background-repeat: no-repeat; margin: 0px 1px 1px 0px;
    border-color: #FFF #505050 #505050 #FFF;
    box-shadow: 1px 1px 4px 2px rgba(185, 185, 185, 0.94);  }

.buttons_REDACTOR:hover { background-color: #CEB867;}

.buttons_REDACTOR:active { padding: 1px 0 0 1px; background-color: #b9b9b9;
    border-color: rgb(80,80,80) rgb(255,255,255) rgb(255,255,255) rgb(80,80,80);
    box-shadow: -2px -2px 7px 5px rgba(161, 161, 161, 0.59) inset; }

.buttons { background-image: none; vertical-align: middle; margin: 0 0 0 4px; width: 41px; height: 41px; float: right }

#help { background-image: none; vertical-align: middle; margin: 0; width: 30px; height: 30px; position: absolute; top: -2px; left: -2px; }
#last_index { background-color: rgb(145, 251, 255); display: inline-block; padding: 2px; font-weight: bold; }

.info_mes { display: inline; margin: 0; padding: 0; line-height: 120% }
.error_mes { display: block; margin: 0; padding: 0; color: red }
#responser .error_mes { font-weight: bold; color: red; font-size: 110%; margin: 5px }
#responser .info_mes { font-weight: bold; font-size: 110%; margin: 5px}

#closeBtn__ { cursor: pointer; float: right; background-color: #FF5C5C; padding: 5px; margin: 0px; width: 30px; text-align: center; font-size: 24px; }

#popup0 { width: auto; height: auto; border: solid 2px; position: absolute; left: 22px; top: 30px; background-color: #FFF3D1; box-shadow: -40px 0px 85px 73px #8C8C8C; display: none; z-index: 1;}
#popup0 .header { margin: 0px; overflow: auto; background-color: rgba(0, 137, 254, 0.65); font-size: 14px; line-height: 20px; text-align: left; }
#popup0 .header .header_text { padding: 4px; display: inline-block; }

#popup1 { font-size: 14px; line-height: 150%; position: absolute; min-width: 500px; max-width: 700px; top: 30px; left: 500px; background-color: white; box-shadow: 50px 0px 185px 83px #8C8C8C; min-height: 300px; border: solid; display: none}
#popup1 p { margin: 3px; padding: 0; line-height: 120% }
#popup1 a.files { display: block; text-decoration: none; position: relative}
#popup1 a.files:hover { color: red; background-color: wheat; text-decoration: underline}
#popup1 a.files:active { color: green}

#popup3 { position: absolute; width: 400px; min-height: 50px; border-radius: 5px; border: solid 2px; background-color: lightyellow; padding: 2px; font-size: 12px; z-index: 1; display: none}

#popup3 * {font-size: 12px}

#popup4 { position: absolute; width: 600px; max-width: 120%; background-color: white; z-index: 3; left: 0; top: 45px; padding: 3px; border: solid 2px; border-radius: 5px; }


#poisk_spravka, #poisk_spravka_addition { max-width: 390px; margin: 5px 5px; font-size: 90%; }
#poisk_spravka_addition { background-color: #C4FFC9 }

#show_logical { background-image: none; float: left; z-index: 2; position: relative; margin: 0 7px 5px 0; vertical-align: middle; height: 20px; width: 20px;}
#fuzzy { background-image: none; z-index: 2;  height: 25px; width: 25px; }
#Notepad { background-image: none; vertical-align: middle; margin-left: 15px; width: 41px; }
#keywords { width: 400px; height: 150px; display: block }
#DO_working_stop { background-image: none; vertical-align: middle; position: relative; float: right; margin: 4px 0 0 0; max-width: 41px }
#last_managed_string { width: 150px; }
#file_name, #index { font-weight: bold; }

#show_buttons_block { position: absolute; width: 100%; background-color: rgba(239, 213, 164, 0.75); height: 100%; }
#show_buttons_block input { background-image: none; vertical-align: middle; margin: 0 0 0 4px; width: 25px; height: 25px; float: right }


button.poisk { padding: 6px; display: block; font-size: 120%; float: right; cursor: pointer; margin: 5px; }

.clean_messages { position: absolute; right: -4px; bottom: -30px; }
.clean_messages input { background-image: none; width: 25px}

.not_indexed_strs { font-size: 90%; background-color: #AEFFF4; text-align: center; margin-top: 5px; }
.not_indexed_strs input { background-image: none; width: 25px; vertical-align: middle; }
.button_error_log { background-image: none; vertical-align: middle; margin-left: 15px; float: right; width: 41px; height: 41px }

.tmpID_1 { display: inline-block; margin: 0; background-color: rgb(174, 255, 174); width: 100% }
.tmpID_12 { min-height: 40px; padding-left: 2px; }
.tmpID_12 > div {  display: inline-block; float: right; }
.tmpID_12 > div > div { position: relative; display: table }

.tmpID_2 { display: inline-block; padding-top: 17px; margin-bottom: 10px; vertical-align: top; }
.tmpID_2 > div, .tmpID_2 > div > .ins_number_str { display: inline-block; position: relative; }
.tmpID_2 > div > .show_number_str { margin-top: 5px; }
.tmpID_2 > div > .show_number_str > button { margin: 5px 20px; cursor: pointer; padding: 3px }
.tmpID_2 > .do_indexing { vertical-align: top; max-width: 100px; text-align: right; }
.tmpID_2 > .do_indexing > div { display: inline-block; vertical-align: top }


.open_poisk { overflow: auto; }
.open_poisk > div { float: right; background-color: #74E36D; margin-top: 20px }
.open_poisk > div > div { display: table; float: right; }
.open_poisk > div > div > div { display: table-cell; vertical-align: bottom }


ol { margin: 0; padding: 5px 30px;}

</style>

</head>
<body>


<div style="display: table">
<!-- Панель управления -->
<div id="panel">

    <div id="tmpID">
        <div class="tmpID_1">

            <div>
                <input id="help" src="imgs/Help.png" onmouseover="change_picture(this, 'show', 'imgs/Help-hover.png', 'popup4')" onmouseout="change_picture(this, 'hide', 'imgs/Help.png', 'popup4')" title="Кликните мышью, чтобы зафиксировать всплывающее окно" onclick="fix_unfix_popup(this, 'popup4')" alt="Справка" type="image"/>
                <!--  Атрибут data-popup_fixed управляет разрешением/запретом скрытия всп. окна при уведении мыши от вышележащего тега  -->
                <div id="popup4" style="display: none" data-popup_fixed="0">Перед началом работы с поисковой системой нужно:
                    <p>1. Полностью проиндексировать ИМЕНА файлов сайта.</p>
                    <p>2. Сделать сортировку словаря. </p>
                    <p>3. Сделать полное индексирование СОДЕРЖИМОГО ВСЕХ файлов сайта. Если ранее индексирование проводилось, но необходимо снова сделать полное индексирование, перед этим следует вначале удалить каталог <span style="font-weight: bold; display: block; margin: 10px"><?php echo str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path_DIR_name); ?></span> вместе с его подкаталогами и файлами (0). </p>
                    <p>После этого можно осуществлять как обычный, так и НЕчеткий поиск. </p>
                    <p>Если на сайте появляются новые файлы или модифицируются уже имеющиеся, нет необходимости проводить индексирование заново. Вместо этого можно:</p>
                    <p>4.1. Запустить индексирование ИМЕН только НОВЫХ или ИЗМЕНЕННЫХ файлов сайта. Будет ОБНОВЛЕН (и/или дополнен) уже имеющийся сводный файл-перечень со списком индексируемых файлов, имеющиеся там индексы сохранятся. После этого <span style="font-weight: bold; text-decoration: underline">обязательно</span>:</p>
                    <p>4.2. Запустить индексирования СОДЕРЖИМОГО - только для НОВЫХ или ИЗМЕНЕННЫХ файлов (из списка, содержащегося в файле-перечне). При этом содержимое таких файлов проиндексируется и они будут корректно участвовать в поиске.</p>
                </div>
            </div>

            <div class="tmpID_12">

                <div>
                    <div>
                    <input id="DO_working" src="imgs/go.png" onclick="DO_working(true, 0)" class="buttons_REDACTOR buttons" title="Запуск индексирования СОДЕРЖИМОГО ВСЕХ файлов, начиная с указанной или сохраненной на сервере строчки (из списка, содержащегося в файле
<?php echo realpath(PATH_FILE_NAMES_ALL_FILES); ?>)" alt=Индексирование" type="image"/>
                    <input src="imgs/sort1.png" onclick="ru_dic_SORTER('')" class="buttons_REDACTOR buttons" title="Сортировка словаря. Будет заново создан и ПРОИНДЕКСИРОВАН словарь, отсортированный по алфавиту от А до Я. Старый файл сохранен под именем
<?php echo str_replace('\\', '/', $ru_dic_FILE_NAME_saved); ?>" alt="Сортировать" type="image"/>
                    <input src="imgs/indexing-files-ALL.png" onclick="file_FINDER('ALL')" class="buttons_REDACTOR buttons" title="Запустить ЗАНОВО индексирование ИМЕН всех файлов сайта. Будет УДАЛЕН, а затем создан НОВЫЙ сводный файл-перечень со списком индексируемых файлов:
<?php echo realpath(PATH_FILE_NAMES_ALL_FILES); ?>" alt="Индексировать" type="image"/>
                    <input src="imgs/del-dir.png" onclick="del_endexed_FILES_DIRS('')" class="buttons_REDACTOR buttons" title="Удалить ВСЕ индексные файлы (вместе с их каталогами)? Т.е. будет полностью, со всеми вложенными подкаталогами и файлами, УДАЛЕН каталог:
<?php echo str_replace('\\', '/', $path_DIR_name); ?>" alt="УДАЛИТЬ индексные файлы" type="image"/>
                        <div id="show_buttons_block" title="Пока эти кнопки заблокированы. Чтобы снять блокировку, нажмите на красный крестик">
                            <input src="imgs/delete-40_res.png" class="buttons_REDACTOR" title="Снять блокировку этих кнопок" alt="Снять блокировку" type="image"/>
                        </div>
                    </div>
                    <input id="DO_working_stop" src="imgs/Close-Cancel.png" onclick="DO_working(false, 0)" class="buttons_REDACTOR" title="Остановить выполняемую операцию" alt="Стоп" type="image"/>
                </div>

                Файл: <span id="file_name">Ни один из файлов не индексируется!... Возможно, все файлы были проиндексированы.</span>
            </div>

            <div style="padding: 2px;">
                Этот файл содержится в <span><?php echo basename(realpath(PATH_FILE_NAMES_ALL_FILES)); ?></span> в строке номер: <span id="index">--</span></div>
        </div>

        <div class="tmpID_2">
            <div>
                <span>Задать номер строки в файле <span><?php echo basename(realpath(PATH_FILE_NAMES_ALL_FILES)); ?></span>, с которого нужно начать<br/> (продолжить) индексирование:</span>

                <div class="ins_number_str">
                    <input id="last_managed_string" placeholder="1..." title="Вставьте индекс-номер файла, если нужно продолжить индексирование именно с этого файла" type="text"></div>

                <div class="show_number_str"><button onclick="ask_string_number(); return false;" title="Если предыдущий процесс индексирования файлов был прерван браузером или в результате ошибки, то сервер мог сохранить, но НЕ УСПЕТЬ отправить браузеру последний номер строки">Показать номер строки, сохраненный на сервере  <br/>(чтобы с этого номера начать <br/>последующее индексирование)<span></span></button></div>
            </div>

            <div class="do_indexing">
                <div><input src="imgs/indexing-files.png" onclick="file_FINDER(null)" class="buttons_REDACTOR buttons" title="Запустить индексирование ИМЕН только НОВЫХ или ИЗМЕНЕННЫХ файлов сайта. Будет ОБНОВЛЕН (и/или дополнен) уже имеющийся сводный файл-перечень со списком индексируемых файлов:
<?php echo realpath(PATH_FILE_NAMES_ALL_FILES); ?>" alt="Индексировать" type="image"/></div>

             <div><input src="imgs/go_refresh.png" onclick="DO_working(true, 1)" style="background-image: none; vertical-align: middle; position: relative; width: 41px" class="buttons_REDACTOR" title="Запуск индексирования СОДЕРЖИМОГО - только для НОВЫХ или ИЗМЕНЕННЫХ файлов (из списка, содержащегося в файле
<?php echo realpath(PATH_FILE_NAMES_ALL_FILES); ?>)" alt="Запуск" type="image"/></div>

            <div lang="ru" class="hyphens not_indexed_strs">Показать номера НЕпроиндексированных строк:<input src="imgs/find.png"  class="buttons_REDACTOR" title="Показать номера строк из файла files.txt, которые еще не были проиндексированы" onclick="find_NOT_INDEXED_strings()" alt="FIND->" type="image"/></div>
            </div>
        </div>
        
<div>
<p style="display: inline-block; margin: 0 2px">Всего файлов, которые будут индексироваться:
    <span id="last_index"><?php echo sizeof($ALL_files_Arr); ?></span></p>
        <input id="Notepad" src="imgs/notepad_pp-40.png" class="buttons_REDACTOR" title="Открыть файл со списком индексируемых файлов Notepad++
<?php echo realpath(PATH_FILE_NAMES_ALL_FILES); ?>" alt="Открыть файл со списком индексируемых файлов Notepad++" type="image"/>

        <input src="imgs/error_log.png" class="buttons_REDACTOR button_error_log" title="Открыть лог-файл  журнал ошибок" onclick="open_error_log()" alt="Открыть файл ошибок" type="image"/>
</div>

<div class="open_poisk">
    <div>
        <input id="show_logical" title="Поставьте галочку, чтобы вместе с результатами показать логическое выражение, используемое для поиска. Актуально для контроля нечеткого поиска" type="checkbox"/>
        <div>
            <div><input id="fuzzy" onchange="fuzzy_finding('fuzzy', 'onchange')" class="buttons_REDACTOR" title="Поставьте галочку, чтобы использовать нечеткий поиск" type="checkbox"/></div>
            <input src="imgs/find-files-indexed.png" style="background-image: none; float: right; z-index: 2; vertical-align: bottom; width: 41px;" onclick="show_hide_POPUP(['popup0', 'popup1'], null)" class="buttons_REDACTOR" title="Найти файлы по искомым словам" type="image"/>
        </div>
    </div>
    <p style="margin-bottom: 0px;">Общий объем проиндексированных файлов за последнюю <br/>операцию: <span id="total">0</span> кБ</p>
</div>

        <div id="tmpID_response"><div></div></div>

        <div class="clean_messages"><div><input src="imgs/delete-40_res.png" class="buttons_REDACTOR" title="Очистить журнал сообщений (ниже)" onclick="clean_responser('responser')" type="image"/></div></div>

        <div id="popup0">
            <p class="header">
                <span class="header_text">Нечеткий поиск по искомым словам:</span>
                <span id="closeBtn__" title="Закрыть" onclick="show_hide_POPUP(['popup0', 'popup1'], 'hide');"> × </span>
            </p>
            <textarea id="keywords" placeholder="Введите слова для поиска по индексированным файлам сайта...   Допускаются логические выражения, например: Слово1 && (Слово2 || слово3)...  Слово1 и (Слово2 или слово3)..."></textarea>
            <button class="poisk" title="Выполнить нечеткий поиск (с использованием функции metaphone)" onclick="find_keywords()">Поиск</button>
            <p id="poisk_spravka">Поиск будет регистро-НЕзависимым.<br/>Допускается не более <span id="max_len"><?php echo $max_keywords_LEN; ?></span> символов, включая пробелы.<br/>Допустимы русские, английские буквы, пробелы <br/>(аналоги &amp;&amp;), скобки и логические операторы &amp;&amp; || (и или). </p><p id="poisk_spravka_addition"></p>
        </div>

        <div id="popup1"></div>

    </div>
    <div id="responser">
        <!--Фиктивный блок, нужен для обтекания (под кнопкой очистки)-->
        <div style="float: right; width: 30px; height: 30px"></div>
    </div>


</div>

<div id="popup3"></div>



<script type="text/javascript">
/* <!-- [CDATA [*/

var source;

fuzzy_finding('fuzzy', null); // добавляем или убираем дополнительную информацию о нечетком поиске на панель

// Скрываем всп. окно с description (или начальной частью файла)
document.getElementById('popup3').onmouseout = function () {
    var $this = this;
    var timer = setTimeout(function () {
        $this.style.display = 'none';
    }, 500);

document.getElementById('popup3').onmouseover = function () { // Если успели вернуть мышь обратно, то отключаем таймер и оставляем всп. окно
    clearTimeout(timer);
 };
};


// Функция снимает защиту (блокировку) с кнопок полного индексирования файлов, словаря
document.getElementById('show_buttons_block').onclick = function (e) {
    e.stopPropagation();

    var target = e.target || e.srcElement;

    switch (target.nodeName.toLowerCase()){
        case 'div':
            alert('Эти кнопки запускают процесс полного индексирования имен файлов сайта, а также их содержимого. Чтобы начать это, нужно вначале снять блокировку, нажав на красный крестик');
            break;
        case 'input':
            this.style.display = 'none';
            break;

        default:
            alert( "Нет таких действий. Похоже - ошибка в обработчике клика для блока с id='"+ this.id+ "'");
    }
};



// Клавиши Escape, F1
document.onkeydown = function ( event ){

    if ( event.keyCode === 27 ) { // Escape
// 1. Скрываем ВСЕ (точнее, не более 100) всп. окна вида popup0, popup1, popup2, ...
    var popups = [];
    var popups_max_num = 100; // Можно и больше, это с запасом

        for(var i=0; i < popups_max_num; i++){
            if(document.getElementById('popup' + i)){
                popups.push('popup' + i);
            }
        }
        show_hide_POPUP(popups, 'hide');

// 2. Выполняем дополнительные специфические действия, специфичные для конкретного всп. окна
        document.getElementById('popup4').setAttribute('data-popup_fixed', 0); // Принудительно устанавливаем атрибут в 0, разрешающий скрытие этого всп. окна при уведении мыши
        document.getElementById('help').title = 'Кликните мышью, чтобы зафиксировать всплывающее окно';
    }

    if ( event.keyCode === 112 ){ // F1
// 1. Показываем справку о работе данной программы (поиска)
        document.getElementById('popup4').style.display = 'block';
    }

};

//Функция выдает/убирает предупреждение и информацию о нечетком поиске
function fuzzy_finding(idd, par) {
    if(document.getElementById(idd).checked){
        if(par === 'onchange'){ // Если запуск функции произошел после события onchange мыши
//            alert('НЕчеткий поиск позволяет найти те же слова, но с разными окончаниями. Использование НЕчеткого поиска - гораздо дольше, чем обычный поиск.');
        }
        document.getElementById('poisk_spravka_addition').innerHTML = 'При использовании нечеткого поиска, помимо самого искомого слова, будут искаться <span style="font-weight: bold; font-size: inherit">также близкие к нему слова</span>, т.е. имеющие разные (допустимые) окончания, если такие слова есть в русском языке. Использование НЕчеткого поиска - дольше, чем обычный поиск.';

    }else{
        document.getElementById('poisk_spravka_addition').innerHTML = '';
    }
}


// Функция далет запрос на открытие программы Notepad++, а в ней - файла-лога ошибок
function open_error_log() {
    var focus_line = 1;
    var focus_offset = 0;
    var body = 'Notepad_PP=Notepad_PP_error_log&n=' + focus_line + '&c=' + focus_offset + '&r=' + Math.random();
    var arg_Arr = ['Notepad_PP_error_log'];
    var url_php = '<?php echo  str_replace('\\', '/', $_SERVER["PHP_SELF"]); ?>';

    DO_send_data1(Function_after_server, arg_Arr, url_php, body);
}

// Функция показывает/скрывает всп. окно (или несколько окон). Например, окно для поиска по искомым словам или иные всп. окна
function show_hide_POPUP(popups, display) {

    if(display === 'hide'){
        change_display(popups, 'none')
    }else{
        if(getComputedStyle(document.getElementById(popups[0])).display === 'none'){
            change_display(popups, 'block')
        }else {
            change_display(popups, 'none')
        }
    }

function change_display(popups, x) {
    for(var i1=0; i1 < popups.length; i1++){
        document.getElementById(popups[i1]).style.display = x;
    }
 }

}

// Функция меняет картинку на теге при наведении или удалении мыши
function change_picture(tag, to_do, srcc, idd) {
    if(tag.nodeName.toLowerCase() === 'input'){
        tag.src = srcc; // Меняем иконку

        var popup = document.getElementById(idd);

        if(!popup){ // А вдруг, мало ли что
            alert('Почему-то отсутствует всплывающее окно с id='+ '. Такое впечатление, что оно удалено или его вообще не было в HTML-коде. А такого быть не должно. Проверьте, пожалуйста, корректность работы браузера, нет ли ошибок в его консоли.');
            return;
        }

        if(popup.getAttribute('data-popup_fixed') === '1'){ // Значит, скрывать всп. окно запрещено
            return;
        }

        show_hide_POPUP([idd], to_do);
    }
}

// Функция задает в теге атрибут, разрешающий (0) или запрещающий (1) скрытие всп. окна при уведении мыши. И, попутно, скрывает или показывает окно.
function fix_unfix_popup(tag, idd) {
    var popup = document.getElementById(idd);

    if(popup.getAttribute('data-popup_fixed') === '0'){
        popup.setAttribute('data-popup_fixed', 1); // Фиксируем окно на экране. Теперь оно не будет скрываться при уведении мыши
        popup.style.display = 'block';
        tag.title = 'Убрать эту информацию';
    }else{
        popup.setAttribute('data-popup_fixed', 0);
        popup.style.display = 'none';
        tag.title = 'Кликните мышью, чтобы зафиксировать всплывающее окно';
    }
}


// Функция делает запрос на сервер для поиска по (нечетким) искомым словам
function find_keywords() {
    var text = document.getElementById('keywords').value.toLowerCase();
    var max_len = parseInt(document.getElementById('max_len').textContent);
    var logic_operands = ['&&', '||', 'и', 'или'];

    if(text.length > max_len){
        alert('Общая длина искомых слов, включая пробелы и логические символы, не может превышать ' + max_len + ' символов');
        return;
    }
    var reg = <?php echo $reg_keywords; ?>; /* /[^абвгдеёжзийклмнопрстуфхцчшщъыьэюя\sqwertyuiopasdfghjklzxcvbnm&\|\(\)]/  */
    if(text.match(reg)){ // Если обнаружится иной символ
        alert('В искомых словах допускаются только пробелы, русские или английские буквы. \nТакже допустимы логические выражения с символами (   )  &&  ||');
        return;
    }

    if(!text){
        alert('искомые слова не заданы!');
        return;
    }

    var text_words_Arr = text.trim().replace(/\s+/g, ' ').split(' ');

    var min_len = <?php echo $min_WORD_len; ?>;

    var text_1_2_Arr = text_words_Arr.filter(function (el) {
        return (el.length < min_len) && !(logic_operands.indexOf(el) !== -1);
    });
    if(text_1_2_Arr.length > 0){
        var text_1_2 = text_1_2_Arr.join(', ');
        alert('Предупреждение: слишком короткие искомые слова (короче '+ min_len+ ' символов) НЕ будут отыскиваться. Это, в частности, слова:\n'+ text_1_2);
    }

    document.getElementById('popup1').innerHTML = ''; // Очищаем область для сообщений от сервера
    var fuzzy = document.getElementById('fuzzy').checked ? 1 : 0;
    var show_logical = document.getElementById('show_logical').checked ? 1 : 0;

    var body = 'find_keywords='+ encodeURIComponent(text)+ '&fuzzy=' + fuzzy+ '&show_logical='+ show_logical + '&r=' + Math.random();
    var arg_Arr = ['find_keywords', 'popup3'];
    var url_php = 'keywords_FINDER.php';

    DO_send_data1(Function_after_server, arg_Arr, url_php, body);

// Создаем заставку, пока ожидается ответ от сервера
    zastavka('popup1');
}

// Функция делает запрос на удаление всех индексных каталогов/файлов (и сам каталог metaphones)
function del_endexed_FILES_DIRS(mess) {
    var dir = '<?php echo str_replace('\\', '/', $path_DIR_name); ?>';
    if(!confirm('Удалить каталог '+ dir+ ' ? При этом будут удалены ВСЕ индексные файлы и содержащие их вложенные каталоги, что сделает НЕВОЗМОЖНЫМ поиск среди индексированных файлов. \n\nИ, да, удаление займет некоторое время...')){
        return;
    }

    var body = 'del_endexed_FILES_DIRS=null' + '&r=' + Math.random();
    var idd = 'rezults';
    var arg_Arr = ['del_endexed_FILES_DIRS', idd];
    var url_php = '<?php echo  str_replace('\\', '/', $_SERVER["PHP_SELF"]); ?>';

    document.getElementById(idd).innerHTML = '<div id="rezults_time"></div><div id="rezults_counter"></div>';

    DO_send_data1(Function_after_server, arg_Arr, url_php, body);

    zastavka('rezults_time');
}


// Функция делает запрос на сортировку файла словаря ru.dic (взятого из Firefox-36) в алфавитном порядке
function ru_dic_SORTER(mess) {

    if(!confirm(mess + '\n\nНачать сортировку словаря <?php echo str_replace('\\', '/', $ru_dic_FILE_NAME); ?>? Это займет некоторое время...')){
        return;
    }

    var body = 'sort_ru_dic=null' + '&r=' + Math.random();
    var idd = 'rezults';
    var arg_Arr = ['sort_ru_dic', idd];
    var url_php = '<?php echo  str_replace('\\', '/', $_SERVER["PHP_SELF"]); ?>';

    document.getElementById(idd).innerHTML = '<div id="rezults_time"></div><div id="rezults_counter"></div>';

    DO_send_data1(Function_after_server, arg_Arr, url_php, body);

    document.getElementById('file_name').innerHTML = ''; // Очищаем информ. область

    zastavka('rezults_time');
    server_Listening(true, "Начинаем индексацию файла-словаря...<br/>", "<b>Индексация словаря остановлена.</b>", "dic_sorting_events.php", 'rezults_counter', 'rezults_counter', '', false); // Прослушиваем события сервера (будет сообщать периодически номер очередной проиндексированной строки в файле-словаре
}

// Функция делает очистку блока для сообщений (журнала), расположенного сразу ниже панели
function clean_responser(idd) {
    document.getElementById(idd).innerHTML = '<div style="float: right; width: 30px; height: 30px"></div>'; // Вставляет фиктивный блок для обтекания
}


// Функция делает запрос на индексирование или на прекращение его
function DO_working(flag_working, flag_time_matter) {
// flag_time_matter - имеет ли значение время создания/модификации файла files.txt и время последней модификации индексируемых файлов (0 или 1)
    var mess;

    if(!flag_working){
        mess = "<b>Останов индексации.</b>";
    }else if(flag_time_matter === 0){
        mess = "Начинаем индексацию содержимого ФАЙЛОВ сайта...<br/>";
    }else {
        mess = "Начинаем индексацию содержимого новых и недавно измененных ФАЙЛОВ сайта...<br/>";
    }

    if(flag_working && !confirm('Начать/продолжить индексирование файлов сайта? Это займет некоторое время...')){
        return;
    }

    var last_managed_string = document.getElementById('last_managed_string').value;

    if(!last_managed_string && flag_working){
        alert('Введите номер строки из файла files.txt, с которого нужно начать/продолжить индексирование. Если не знаете, с какого номера начать, введите 1');
        return;
    }

    var url_php = '<?php echo  str_replace('\\', '/', $_SERVER["PHP_SELF"]); ?>';
    var body = 'DO_working='+ flag_working+ '&last_managed_string='+ last_managed_string+ '&flag_time_matter='+ flag_time_matter+ '&r='+ Math.random();
    var arg_Arr = ['DO_working'];

    document.getElementById('file_name').innerHTML = ''; // Очищаем область показа текущего индексируемого файла

    if(!flag_working){
        DO_send_data1(Function_after_server, arg_Arr, url_php, body);
    }

    server_Listening(flag_working, mess, "<b>Стоп...</b>", "file_INDEXER_events.php?"+ body, 'rezults', 'rezults', 'rezults', true); // Прослушиваем события сервера (будет сообщать периодически имя очередного проиндексированного файла
}


document.getElementById('Notepad').onclick = function() {
    var focus_line = 1;
    var focus_offset = 0;
    var body = 'Notepad_PP=Notepad_PP&n=' + focus_line + '&c=' + focus_offset + '&r=' + Math.random();
    var arg_Arr = ['Notepad_PP'];
    var url_php = '<?php echo  str_replace('\\', '/', $_SERVER["PHP_SELF"]); ?>';

    DO_send_data1(Function_after_server, arg_Arr, url_php, body);
};

// Функция запрашивает номер строки в файле files.txt, который содержит относит. путь к файлу сайта, индексировавшемуся в предыдущий раз
function ask_string_number() {
    var body = 'ask_string_number=null' + '&r=' + Math.random();
    var arg_Arr = ['ask_string_number'];
    var url_php = '<?php echo  str_replace('\\', '/', $_SERVER["PHP_SELF"]); ?>';

    DO_send_data1(Function_after_server, arg_Arr, url_php, body);
}

// Функция делает запро на сервер для поиска еще непроиндексированных строк из файла files.txt (в каждой из них содержится относит. путь к файлу)
function find_NOT_INDEXED_strings() {
    var body = 'ask_string_numbers_NOT_indexed=null' + '&r=' + Math.random();
    var arg_Arr = ['ask_string_numbers_NOT_indexed'];
    var url_php = '<?php echo  str_replace('\\', '/', $_SERVER["PHP_SELF"]); ?>';

    DO_send_data1(Function_after_server, arg_Arr, url_php, body);
}

// Функция создает таймер-заставку (счет времени по секундам) в блоке, ожидающем ответа сервера
// Когда исчезает элемент с классом "waiting", таймер останавливается
function zastavka(idd, Function_name, funct_arg_Arr) {
    document.getElementById(idd).innerHTML = '<span class="waiting">'+ (0) + ' сек.</span>';
    var i=0;
    var timer = setInterval(function () {

        if(!document.getElementById(idd)){
            clearInterval(timer);
            return;
        }

        if(!document.getElementById(idd).querySelector('[class="waiting"]')){
            clearInterval(timer);
        }else {
            document.getElementById(idd).innerHTML = '<span class="waiting">'+ (i++) + ' сек.</span>';
//            Function_name(funct_arg_Arr); // Через промежуток время запускаем другую функцию
        }
    }, 1000);
}




// Функция запускает/останавливает серверное событие
function server_Listening(flag, go, end, events_php, id_go_mes, id_recieve_mes, id_stop_mes, flag_adding) {
    var rezults;

        if(flag){ // Запускаем серв. событие

            try { // Перед очередным запуском (например, если повторно нажата кнопка запуска) останавливаем предыдущее соединение, если оно было
                source_close('Соединение EventSource прекращено перед установлением нового.');
            }catch (er){
                console.log(er);
            }
// Если так, то браузер будет периодически инициировать новое соединение при отказе сервере. Это можно исправить путем отправки сервером кода состояния, не равного 200 (например, 202 или 204). Тогда браузер прекратит инициировать новые соединения
//            const source = new EventSource(events_php);
            source = new EventSource(events_php);

        source.onmessage = function (event) {
            var rezults = document.getElementById(id_recieve_mes); // Сюда помещаются сообщения, отправляемые сервером по событию

            var event_data = event.data;

            if(flag_adding){
                rezults.innerHTML += event_data;
            }else{
                rezults.innerHTML = event_data;
            }

            var matches = event_data.match(/<script[^>]*>[\s\S]*?<\/script>/g) || [];

            for(var i=0; i < matches.length; i++){ // Находим все скрипты в ответе сервера (если они есть) и запускаем их по очереди
                var scr = matches[i].match(/<script[^>]*>([\s\S]*?)<\/script>/);

                if(scr[1]){ // Содержимое между тегами <scr ipt>... </scr ipt>
                    var script = document.createElement('script');
                    script.defer = true;
                    script.text = scr[1];
                    document.body.appendChild(script);
                }
            }
        };

        source.onopen = function (event) {
            console.log('Соединение EventSource установлено...');
        };

        source.onerror = function (error) { // Ошибка может возникнуть, например, когда скрипт на сервере, обслуживающий EventSource, прекратит работу
            console.log('Ошибка EventSource:');
            console.log(error);

            var er_mes = '';
            if(!source){
                er_mes = 'Объект EventSource не существует';
            }else{
                if(!source.CONNECTING){
                    er_mes = 'Соединение EventSource прервано сервером.'
                }else {
                    er_mes = 'Соединение EventSource существует, но произошла неизвестная ошибка с EventSource.';
                }
            }

            source_close(er_mes);
        };

        rezults = document.getElementById(id_go_mes);
        rezults.innerHTML = "" + go;
    }

    if(!flag || flag === 'false'){ // Останавливаем серв. событие

        source_close('Соединение EventSource прекращено.');
        rezults = document.getElementById(id_stop_mes);
        rezults.innerHTML += "<br>" + end;
    }

function source_close(mess) {
    try {
        source.close();
        source = null;
        console.log(mess);

        document.getElementById('file_name').innerHTML = '<span class="error_mes">Разорвано соединение с сервером. Выполняемая операция прекрашена. Для возобновления - повторите операцию.</span>';

    }catch (er){
        console.log('Ошибка при попытке прекратить соединение EventSource. Вероятно, такого соединения еще (уже) нет.');
    }
 }

}



// Функция запускается скриптами, периодически приходящими с сервера
function show_index(number, file_name, total_size) {
// Помещает присланный номер строки (из файла files.txt), в которой содержится относит. путь к только что проиндексированному файлу, в блоки:
    document.getElementById('index').textContent = number;
    document.getElementById('file_name').textContent = file_name;
//    document.getElementById('last_index').textContent = number;
    document.getElementById('last_managed_string').value = number;
    document.getElementById('total').textContent = Math.round(total_size/1024 * 100)/100; // Общий размер проиндексированных файлов с момента последнего запуска индексирования
}

// Функция запускается скриптами, периодически приходящими с сервера: дублирует сообщения об ошибках в блок id="responser"
function manage_message() {
    document.getElementById('responser').innerHTML += document.currentScript.previousSibling.outerHTML;
}

// Функция делает запрос на сервер для получения еще  ссылок на файлы, содержащие искомые слова
function show_links_in_POPUP(idd) {

    var link_last_num = document.getElementById('num_showed_links').textContent; // Отправляем общее число ссылок на странице. чтобы сервер знал, с какого номера продолжить выдачу ссылок
    var body = 'ask_keyword_links='+link_last_num + '&r=' + Math.random();
    var arg_Arr = ['ask_keyword_links'];
    var url_php = 'keywords_FINDER.php';

    DO_send_data1(Function_after_server, arg_Arr, url_php, body);
}


/*]] --> */
</script>


<div id="rezults">

</div>


</div>


</body>
</html>


<?php

/**********    ФУНКЦИИ    *************/

// Функция индексирует содержимое ФАЙЛОВ сайта (по перечню имен, приведенному в файле files.txt)
function indexer($predlogi_PATH, $min_WORD_len, $internal_enc, $path_FILE_name_STRING, $ALL_files_Arr, $max_UNIX_saved, $DO_working_flag_FILE, $ENC_FILE_names_all_files, $enc_Arr, $total_size, $begins, $ends, $path_DIR_name, $path_common_index_name, $metaphone_len, $path_FILE_max_UNIX, $str_UNIX_begin, $str_UNIX_end, $JS_manage_mes, $min_index_FILE_len){

// 6. Работаем с русскими предлогами (союзами и т.п.)
    $predlogi_Arr = file($predlogi_PATH); // Берем практически все известные предлоги, частицы, союзы, междометия, местоимения русского языка
    $predlogi_Arr = array_map('trim', $predlogi_Arr);

    $predlogi_Arr = array_filter($predlogi_Arr, function ($el) use ($min_WORD_len, $internal_enc){ // Берем только слова не короче $min_WORD_len символов
        return (mb_strlen($el, $internal_enc) >= $min_WORD_len);
    });

    $predlogi_Arr = array_unique($predlogi_Arr); // Убираем повторяющиеся слова из массива
    file_put_contents('predlogi_DATA.txt', implode(PHP_EOL, $predlogi_Arr));

    $i_begin = 0;
    if(is_readable($path_FILE_name_STRING)){ // Если ранее уже проводилось индексирование и был запасен индекс-номер последнего проиндексированного файла
        $i_begin = 1*(trim(file_get_contents($path_FILE_name_STRING))) - 1;
    }else{
        file_put_contents(PATH_FILE_NAMES_ERROR, 'Ошибка - '. date("d.m.Y - H:m:s") . PHP_EOL. 'Почему-то файл '. $path_FILE_name_STRING. ' недоступен для чтения' , FILE_APPEND);
    }

    if($i_begin < 1){ // Начинаем со 2-й строчки из файла files.txt, т.к. первая строчка там - информационная; она содержит максимальное значение метки UNIX среди (индексируемых) файлов сайта.
        $i_begin = 0;
    }

    if($max_UNIX_saved > 0){
// Если будет делаться не полное (начиная с некоторой строки), а дополнительное индексирование СОДЕРЖИМОГО только НОВЫХ или ИЗМЕНЕННЫХ файлов сайта, то начинаем с первой строки в файле-перечне files.txt. Потому что некоторые файлы, имена которых находятся даже в первых его строчках, могли быть изменены.
        $i_begin = 0;
    }

echo $i_begin.'!';

    $i_begin_Arr = array_keys($ALL_files_Arr);
    $i_begin_key = $i_begin_Arr[$i_begin]; // Ключ массива для элемента с номером $i_begin


    $flag_is_indexing = false; // Пока еще индексирование не выполняется

    /*****************************************************************************/
    /********      7. ИНДЕКСИРУЕМ КАЖДЫЙ ФАЙЛ (с именем ИЗ МАССИВА-перечня ФАЙЛОВ)      *************/
// По массиву файлов сайта, разрешенных (или не запрещенных) к индексации. Строки из файла-перечня, которые содержат НЕчисловые значения после | , НЕ учитываются


$numbers_Arr = explode(PHP_EOL, file_get_contents(PATH_FILE_NAME_INDEXED_SUCCESS)); // Повтор +++
$numbers_Arr = array_values(array_unique($numbers_Arr));
file_put_contents(PATH_FILE_NAME_INDEXED_SUCCESS, implode(PHP_EOL, $numbers_Arr)); // Попутно, пересохраняем в файл только уникальные номера строк


    
$time_UNIX_i_MAX = 0; // Для начала

    $indexes_Arr = array_keys($ALL_files_Arr); // Массив индексов, взятых после  |  из files.txt

// foreach ($ALL_files_Arr as $i => $ALL_files_Arr_item) // Так работает некорректно. К сожалению, берет элементы НЕ В ТОМ порядке, в к-ром они выводятся на экран. Разная сортировка не помогает

$mess_Arr = array('Индексирование прекращено.', 'Для начала/продолжения индексирования файлов сайта нужно произвести ЗАПУСК индексирования. Индексирование начнется с номера строки (в файле files.txt), который задан в панели слева.', 'Похоже, прервано соединение с сервером. Процесс индексации словаря остановлен. Попробуйте повторить эту операцию.');

    for($i=$i_begin; $i < sizeof($indexes_Arr); $i++){
            if ($i < $i_begin) continue; //Начинаем с заданной (или ранее сохраненной) строчки

            $ALL_files_Arr_item = $ALL_files_Arr[$indexes_Arr[$i]];

// Проверяем, следует ли прерывать итерацию цикла индексирования файлов
            if(!must_continue($mess_Arr, $DO_working_flag_FILE, $flag_is_indexing, $str_UNIX_begin, $time_UNIX_i_MAX, $str_UNIX_end)){
                break;
            }

        $flag_is_indexing = true; // Если после запуска индексирования была хотя бы 1 итерация, т.е. индексировался хотя бы 1 файл

        set_time_limit(80); // С этого момента скрипт будет выполняться не более указанного количества секунд (каждая итерация цикла).

// 7.2. Из files.txt получаем имя файла, который (возможно) будет индексироваться
        $file_name_Arr = explode(';', $ALL_files_Arr_item);
        $file_name = $file_name_Arr[0]; // Относительный путь к индексируемому файлу

        $file_name_ABS = realpath($_SERVER['DOCUMENT_ROOT']. $file_name); // Абсолютный путь

// 7.3. Если нужно сравнивать времена создания индексируемого файла и макс. метку времени UNIX. Это нужно, когда требуется проиндексировать только НОВЫЕ или недавно измененные файлы (это когда не при создании files.txt заново, а при его обновлении)
        $time_UNIX_i = filemtime($file_name_ABS);

        $time_UNIX_saved = trim(file_get_contents($path_FILE_max_UNIX));
        $time_UNIX_i_MAX = $time_UNIX_saved;

// 7.4. Сохраняем максимальную метку времени UNIX во временный файл
        if(($time_UNIX_i > $time_UNIX_i_MAX)){
            $time_UNIX_i_MAX = $time_UNIX_i;
        // Перед началом индексирования очередного файла это значение записывается в файл, хранящий максимаьную метку UNIX
            file_put_contents($path_FILE_max_UNIX, $time_UNIX_i_MAX);
        }

        if($max_UNIX_saved > 0){ // Т.е. если задан режим индексации только новых и измененных файлов
// Если время создания/модификации индексируемого файла НИЖЕ, чем макс. метка времени UNIX (значит, его НЕ НУЖНО индексировать; это если задан режим индексации только новых и измененных файлов)
            if($max_UNIX_saved >= $time_UNIX_i){
                $mess = '<p class="info_mes" style="display: block; color: #65B965; font-size: 80%;">'.($i+2). '. <span style="font-weight: bold;">' .$file_name. '</span>: -- Пропущен -- </p>';

//                sendMsg(time(), $mess, false);
                continue;
            }
        }


// 7.5. Имя файла перекодированное (только для вывода в виде информации на экран). Актуально, когда в имени файла содержится, например, кириллица
        $file_name_encoded = ($ENC_FILE_names_all_files === $internal_enc) ? $file_name : mb_convert_encoding($file_name, $internal_enc, $ENC_FILE_names_all_files);

        if(!is_readable($file_name_ABS)){ // Если файл вдруг не существует (мало ли...) или заблокирован в данный момент
            $mess = ' Ошибка: файл '.$file_name_encoded. ' не удалось проиндексировать, т.к. он недоступен для чтения';
            $mess = '<p class="error_mes">'. $mess. '</p>'. $JS_manage_mes;
            sendMsg(time(), $mess, false);

            file_put_contents(PATH_FILE_NAMES_ERROR, $file_name. '|'. array_search($ALL_files_Arr_item, $ALL_files_Arr). '|'. $mess.' '.date("d.m.Y - H:m:s") . PHP_EOL , FILE_APPEND);
            continue;
        }else{
            $mess = '<p class="info_mes">'. ($i+2).'. Индексируется файл: <b>'.$file_name_encoded. '... </b></p>';
            sendMsg(time(), $mess, false);
        }

//        flush(); // Чтобы выводить строчки постепенно, одну за другой, а не потом все сразу

// 7.6. Берем содержимое выбранного файла
        $body = file_get_contents($file_name_ABS);

// 7.7. Определяем кодировку содержимого индексируемого файла (пока допустимы только cp1251 и utf-8)
        $enc = check_enc($body, $enc_Arr);
        if(!$enc){
            $mess = ' Не удалось определить кодировку файла '. $file_name_encoded;
            $mess = '<p class="error_mes">'. $mess. '</p>'. $JS_manage_mes;
            sendMsg(time(), $mess, false);

            file_put_contents(PATH_FILE_NAMES_ERROR, $file_name. '|'. array_search($ALL_files_Arr_item, $ALL_files_Arr). '|'. $mess. ' ' .date("d.m.Y - H:m:s"). PHP_EOL , FILE_APPEND);
        }

        if($enc !== strtolower($internal_enc)){
            $body = mb_convert_encoding($body, $internal_enc, $enc);
        }

        $total_size += mb_strlen($body, $internal_enc); // Добавляем объем этого файла

// 7.8. Ищем в содержимом файла теги <body>...</body>
        $body_num = @preg_match_all('|<body[^>]*>([\s\S]*)</body>|', $body, $matches, PREG_SET_ORDER);

        if($body_num === false){
            $mess = ' Ошибка в функции preg_match_all() при работе с файлом '. $file_name_encoded .'; см. Файл '. $_SERVER['PHP_SELF'] . ', стр.'. __LINE__ ;
            $mess = '<p class="error_mes">'. $mess . '</p>'. $JS_manage_mes;
            sendMsg(time(), $mess, false);

            file_put_contents(PATH_FILE_NAMES_ERROR, $file_name. '|'. array_search($ALL_files_Arr_item, $ALL_files_Arr). '|'. $mess. ' '. date("d.m.Y - H:m:s"). PHP_EOL , FILE_APPEND);
        }

        if(sizeof($matches) === 1){ // Если этих тегов ровно 1 пара

            $body = $matches[0][1]; // Содержимое тегов <body>...</body>

// 7.9. Пробуем найти редактируемую область в содержимом файла (она должна быть внутри тегов <body>...</body>). Если она есть, то берем ее содержимое
            $flag_FIND_redact_area = true; // Искать ТОЛЬКО  в редактируемой области (при ее наличии)
// Если в файле задана редактируемая область и задан флаг true (ТОЛЬКО в редактируемой области), то берем только ЕЕ содержимое

            $path_redactorDATA = "/LOCAL_only/REDACTOR/redactorDATA.php";
            if(file_exists($_SERVER['DOCUMENT_ROOT']. $path_redactorDATA)){ // Если найден файл настроек редактора

                require_once $_SERVER['DOCUMENT_ROOT']. $path_redactorDATA;

                $domen = $_SERVER['SERVER_NAME'];
                $domen = preg_replace("'(^www\.)'", "", $domen); // Вырезаем www.  в самом начале, если есть

                $begin = $begins[$domen];
                $end = $ends[$domen];

                if(CHECK_begin_end_finder($begin, $end, $body)){ // Если в файле ЕСТЬ комментарии, ограничивающие редактируемую область

                    $pos_BEGIN = strpos($body, $begin);
                    $pos_END = strpos($body, $end);

                    if($pos_BEGIN !== false && $pos_END !== false){ // Если ограничивающие комментарии найдены в тексте, значит, ЕСТЬ редактируемая область
                        $body = substr($body, $pos_BEGIN, $pos_END - $pos_BEGIN); // Берем только содержимое, содержащееся между ограничивающими комментариями
                    }
                }
            }
        }else{
//    die('В файле '. $file_name_encoded. ' число пар тегов <body>...</body>'. ' НЕ равно 1.');
        }
        $body = preg_replace('~<\?[\s\S]*?\?>|<\?[\s\S]*~', ' ', $body); // Удаляем все теги PHP.
        $body = preg_replace('|<script[^>]*>([\s\S]*?)</script>|i', ' ', $body); // Вырезаем все JS-скрипты (т.к. их индексировать не будем)
        $body = preg_replace('|<[^>]*?>|', ' ', $body); // Вырезаем все теги html, DOCTYPE и комментарии
// Итак, получено текстовое содержимое (textContent) или полное, или, быть может, только между тегами <body>...</body> или, быть может, даже только между ограничивающими комментариями, ограничивающими редактируемую область

// 7.10. Дорабатываем полученную текстовую строку, превращая ее массив метафонов
        $body = preg_replace('|&[^;]*;|', '', $body); // Вырезаем HTML_сущности (т.к. их индексировать не будем)
        $reg = '[^абвгдеёжзийклмнопрстуфхцчшщъыьэюяАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM]'; // для кириллицы параметр i не работает, поэтому берем как строчные, так и заглавные буквы
//$body = preg_replace($reg, ' ', $body); // Работает, НО(!) проблемы с символом №, например

        $body = mb_ereg_replace($reg, ' ', $body); // Вырезаем все, кроме пробелов, русских и английских букв (обоих регистров)
        $body = preg_replace('|\s+|', ' ', $body); // Вырезаем лишние пробельные символы, оставляем только по одному пробелу

        $body_Arr = explode(' ', mb_strtolower($body, $internal_enc)); // Массив слов из (основного) содержимого файла

        $body_Arr = array_filter($body_Arr, function ($el) use ($min_WORD_len) {
            return (mb_strlen(($el)) >= $min_WORD_len); // Оставляем только слова, которые не короче $min_WORD_len
        });


//        $body_Arr = array_unique($body_Arr); // Это если НЕ учитывать число одинаковых слов

        $body_Arr = array_diff($body_Arr, $predlogi_Arr); // Убираем (многие) предлоги, междометия, частицы, местоимения русского языка
        $body_Arr = array_map('translit1', $body_Arr); // В транслит, чтобы можно было применить функцию metaphone()


        $body_Arr = array_map(function ($word) use ($internal_enc, $metaphone_len){
            return do_metaphone1($word, $metaphone_len); // Превращаем в metaphone
        }, $body_Arr);

// Получаем массив уникальных метафонов
//        $body_Arr = array_unique($body_Arr); // Оставляем только уникальные слова, т.е. повторы не учитываем (пока)
        $body_Arr = array_values($body_Arr);

        /**************************************************************************/

// 7.11. Создаем каталог для подкаталогов-символов - частей метафонов
        if(!is_dir($path_DIR_name)){
            mkdir($path_DIR_name);
        }


// 7.12. Создаем индекс-каталоги, в них создаем индекс-файлы, а в последние записываем индекс-номера путей к файлам сайта (если таких номеров еще не было записано)
        if(($number = array_search($ALL_files_Arr_item, $ALL_files_Arr)) !== false){ // Определяем N-ричное число-индекс, присутствующий у имени файла (из содержимого которого выше были получены слова-метафоны). Он находится после |  (в файле files.txt)

            $body_Arr_size = sizeof($body_Arr);
            for($k=0; $k < $body_Arr_size; $k++){ // По каждому из слов (метафонов)

//sendMsg(time(), $body_Arr[$k].PHP_EOL, false);

                if(isset($body_Arr[$k]) && $body_Arr[$k] != ''){
// Массив ключей, соответствующих текущему метафону $body_Arr[$k] (их там м.б. несколько; т.е. учитываем ОДИНАКОВЫЕ метафоны в целях последующего ранжирования)
                    $body_Arr_k_Arr = array_keys($body_Arr, $body_Arr[$k]);


                    $first_letter = substr($body_Arr[$k], 0, 1);
                    $COMMON_file_name = $path_DIR_name. $path_common_index_name. $first_letter. '.txt';

                    if(!is_file($COMMON_file_name)){
                        file_put_contents($COMMON_file_name, '');
                        $str_COMMON_FILE = ''; // Строка-содержимое файла common_*.txt в каталоге metaphones. Ее обнуляем каждый раз, для создаваемого файла common_*.txt
                    }else{ // Если такой файл уже есть, то берем его содержимое
                        $str_COMMON_FILE = file_get_contents($COMMON_file_name);
                    }


                    $rez = LAST_met_2_index($body_Arr[$k], count($body_Arr_k_Arr), '', $file_name, $path_DIR_name, $COMMON_file_name, $number, $ALL_files_Arr, $JS_manage_mes, '', $str_COMMON_FILE, $path_common_index_name, $min_index_FILE_len);

                    foreach ($body_Arr_k_Arr as $elem){
                        unset($body_Arr[$elem]); // Удаляем из массива уже проиндексированные метафоны
                    }

                }
//die();
            }

// 7.13. На всякий случай, делаем контроль ошибок
$mess = check_ERRORS('Error|Произошла ошибка при индексировании файла '. $file_name_encoded. '. Номер строки '. ($i+1). $JS_manage_mes);
if($mess){
    sendMsg(time(), $mess, true); // Если были ошибки, прекращаем работу
}

// 7.14. Если ошибок не было, сохраняем номер проиндексированного файла (это - номер строки, начиная с 1, в файле files.txt). Здесь будет содержаться перечень индексов тех файлов, которые успешно проиндексированы
            file_put_contents(PATH_FILE_NAME_INDEXED_SUCCESS, ($i+2). PHP_EOL, FILE_APPEND);
            file_put_contents($path_FILE_name_STRING, ($i+1). PHP_EOL); // 
        }else{
            $mess = '<p class="error_mes">У файла "'. $file_name. '" не получилось определить индекс. Индекс должен находиться в файле '. PATH_FILE_NAMES_ALL_FILES. ' в строчке '. ($i+2). ' после символа |</p>';
            sendMsg(time(), $mess, false);
            die();
        }


// 7.15. Если дошли до сюда (в каждой итерации цикла перебора индексируемых файлов), то выводим подтверждение об успехе. Также сообщаем номер строки в файле files.txt, содержащей имя только что проиндексированного файла и его индекс
        $mess = '<p class="info_mes">OK</p>'. '<script>show_index('. ($i+2). ',"'. str_replace("\\", "\\\\", $file_name_encoded) .'",'. $total_size. ')</script>'. '<br/>'; // +1, т.к. номерация строк в файле (в Notepad++) начинается с 1, а не с 0. Еще +1, т.к. 1-я строка этого файла не содержит имя файла
        sendMsg(time(), $mess, false);

        flush();

    }

// Перед завершением индексирования сохраняем макс. метку времени UNIX в файл-перечень files.txt
    save_time_UNIX_MAX($str_UNIX_begin, $time_UNIX_i_MAX, $str_UNIX_end);

// 7.16. На всякий случай, окончательно делаем контроль ошибок
    if(!isset($file_name_encoded)){
        $file_name_encoded = ' -- Unknown -- ';
    }
    $mess = check_ERRORS('Error|Произошла ошибка при индексировании файла '. $file_name_encoded. '. Номер строки '. ($i+1). $JS_manage_mes);

    if($mess){
        sendMsg(time(), $mess, true); // При наличии ошибок прекращаем работу
    }

}

// Функция проверяет наличие начального и конечного ограничивающих комментариев (как и в программе для редактирования)
function CHECK_begin_end_finder($begin, $end, $text_html){

// Проверяем наличие начальных комментариев, ограничивающих основной контент страницы. Их должно быть ровно по ОДНОМУ
    if($begin == ""){
return false;
//        die("Эта страница - не для наших сайтов, ее корректировка невозможна (отсутствуют начальные комментарии, ограничивающие начало редактируемого контента). Возможно, следует открыть эту страницу в браузере <span style='font-weight: bold'>БЕЗ www.</span>");
    }
    $beginCOMMS = preg_match_all("/".  preg_quote($begin, '/'). '/', $text_html, $matches);
    $endCOMMS = preg_match_all("/".  preg_quote($end, '/'). '/', $text_html,  $matches);

// Проверка на отсутствие начальных и конечных ограничивающих комментариев. Актуально для файлов, которые могут редактироваться при помощи редактора
    // Начальные
    if($beginCOMMS !== 1 || $endCOMMS !== 1){
        return false;
    }

return true;
 }



// Функция открывает программу notepad++, а в ней - файл с именем $file_to_open
function start_NotepadPP_working1($file_to_open, $n, $c){

    $command = 'start notepad++ -n'. $n .' -c'. $c. ' ' .  $file_to_open;

    echo '<p class="info_mes">Команда открытия Notepad++ выполнена.</p>';

    exec($command, $exec_output, $exec_res_code);

    if($exec_res_code != 0){ // Ошибка открытия notepad++
        print_r($exec_output); // Вывод команды exec в случае ошибки
        $mess = 'В результате попытки открытия файла в программе notepad++ возникла ошибка. Вот ее код: \'. $exec_res_code';
        file_put_contents(PATH_FILE_NAMES_ERROR, $mess. ' '. date("d.m.Y - H:m:s"). PHP_EOL , FILE_APPEND);
        die('<p class="error_mes">В результате попытки открытия файла в программе notepad++ возникла ошибка. Вот ее код: '. $exec_res_code .'</p>>');
    }
 }
