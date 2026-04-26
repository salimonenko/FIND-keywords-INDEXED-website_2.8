<?php
// Программа ищет ВСЕ файлы на сайте и записывает их относительные пути в файл
// В файл будут записаны строки вида: Относительный путь|Номер. Номер - это уникальный индекс этого файла

mb_internal_encoding("utf-8");
$internal_enc = mb_internal_encoding();

//0. Значения по умолчанию
$str_UNIX_begin = '';
$str_UNIX_end = '';

// 1. Задаваемые параметры/функции
require __DIR__ . '/parametrs.php';
require_once __DIR__ . '/common_functions.php';

$forbidden_FILE_extensions_Arr = array('css', 'js', 'csv', 'png', 'pdf', 'bmp', 'jpg', 'jpeg', 'md', 'xls', 'log', 'inc', 'sh', 'json', 'yml', 'db', 'htaccess', 'xml', 'gif', 'doc', 'rtf', 'docx', 'svg', 'svgx', 'rar', 'zip', 'xcf', 'cdw', 'mp3', 'avi', 'mp4', 'webp', 'ico', 'exe', 'mov', 'ini');  // Файлы с такими расширениями НЕ будут просматриваться и, соответственно, НЕ БУДУТ индексироваться
$forbidden_dirs_Arr = array('.idea', '.git', 'img', 'js', 'css', 'SSI', 'TEST', 'LOCAL_only', 'lib', 'metaphones');
$allowed_FILE_extensions_Arr = array('htm', 'html', 'php', 'txt'); // Будут просматриваться файлы только с такими расширениями (из уникальные индексы и относит. пути к этим файлам как раз и будут включены в файл-перечень files.txt)
$flag_allowed_FILE_extensions = true; // Применять (если - true) массив разрешенных расширений файлов

$dir_relative = '/'; // Относительный путь к каталогу, где будет производиться поиск файлов
$entry = '';


header('Content-type: text/html; charset=utf-8');

$t0 = microtime(true);

// 2. Проверяем корректность запроса клиента
$file_FINDER_Arr = array('ALL', 'null'); // Только такие значения разрешены в запросе клиента

if(!isset($_REQUEST['file_FINDER']) || !in_array($_REQUEST['file_FINDER'], $file_FINDER_Arr)){
    die('<p>Неверный запрос браузера.</p>');
}else{
    $file_FINDER_param = $_REQUEST['file_FINDER'];
}


$ALL_files_path = PATH_FILE_NAMES_ALL_FILES; // Абсолютный путь к файлу с перечнем ВСЕХ (незапрещенных и/или разрешенных) файлов сайта

$path = $_SERVER['DOCUMENT_ROOT']. $dir_relative; // Абсолютный путь до начального каталога


$dirs_ALL_Arr = array(); // Массив всех подкаталогов в начальном каталоге
$path_done_Arr = array();

// 3. Получаем массив полных имен каталогов
$dirs_ALL_Arr = look_dir1($path, $entry, $dirs_ALL_Arr, $path_done_Arr, $forbidden_FILE_extensions_Arr, $forbidden_dirs_Arr, $allowed_FILE_extensions_Arr, $ALL_files_path, $i); // В подкаталогах


$i = 0; // Индекс-Номер файла (он потом будет записан в файл-перечень с именем files.txt). Строка-запись будет иметь примерный вид: filename;0|4

if(!$flag_allowed_FILE_extensions){
    $allowed_FILE_extensions_Arr = null;
}

// 4. Определяемся, что нужно делать: заново создавать files.txt или использовать уже имеющийся этот файл (т.е. обновить его)
$files_all_Arr = array();



// 4.1. // В случае, если файл-перечень создается заново (при полном индексировании ВСЕХ незапрещенных файлов сайта)
if($file_FINDER_param === 'ALL' || !file_exists($ALL_files_path)){
    file_put_contents($ALL_files_path, '\\fileName'. $str_UNIX_begin. '0'. $str_UNIX_end. PHP_EOL); // Очищаем файл (или, если файла еще нет, то создаем его)

    $letters_Arr = GET_some_ASCII1(); // Массив некоторых однобайтовых (ASCII) символов для преобразования 10-чных индексов в N-ричные индексы

    for($j=0; $j < sizeof($dirs_ALL_Arr); $j++){ // По каждому из (не запрещенных) каталогов

        $entry = $dirs_ALL_Arr[$j];
// Массив файлов в текущем каталоге (только файлы, без каталогов)
        $files_Arr = FILES_in_DIR($entry, $forbidden_FILE_extensions_Arr, $allowed_FILE_extensions_Arr);
        save_FILES_NAMES($files_Arr, $entry, $ALL_files_path, $i, $letters_Arr); // Выводим (записываем в файл) имена файлов
    }

$mess = 'Создан файл: <br/><br/><b>'. $ALL_files_path. '</b><br/><br/> с именами разрешенных/незапрещенных (для индексации) файлов сайта. <br/><br/>Теперь <b>НЕОБХОДИМО</b>  запустить сортировку и индексацию словаря. <br/><br/>Затем <b>НЕОБХОДИМО</b> индексирование ВСЕХ файлов сайта (иначе поиск будет происходить НЕПРАВИЛЬНО). В результате индексирования будет создан/обновлен каталог <b>/'. basename($path_DIR_name). '</b> с содержащимися там каталогами, сообразно символам метафонов слов контента файлов сайта, а также файлами 1.txt.';

// 4.2. В случае, если требуется доиндексировать только изменившиеся или новые файлы (т.е. обновить файл-перечень)
}elseif ($file_FINDER_param === 'null'){
    $files_all_Arr = explode("\n", file_get_contents($ALL_files_path)); // Массив всех строк из файла-перечня files.txt
    $files_all_Arr = array_map('trim', $files_all_Arr); // Удаляем символы \r
    $files_all_Arr = array_values($files_all_Arr);


$files = file_get_contents($ALL_files_path);
$max_UNIX_val = 0; // Максимальная (по всем индексируемым файлам) метка UNIX (для начала)

    for($j=0; $j < sizeof($dirs_ALL_Arr); $j++){ // По каждому из (не запрещенных) каталогов

        $entry = $dirs_ALL_Arr[$j]; // Выбранный каталог
// Массив файлов в текущем каталоге (только файлы, без каталогов)
        $files_Arr = FILES_in_DIR($entry, $forbidden_FILE_extensions_Arr, $allowed_FILE_extensions_Arr);
        $files = save_FILES_NAMES2($files_Arr, $files, $entry, $max_UNIX_val); // Обновляем содержимое (только метки UNIX) файла-перечня files.txt. Индексы НЕ трогаем
    }
// В 1-ю строку файла files.txt устанавливаем максимальную метку UNIX среди всех файлов, имена которых присутствуют в files.txt
//    $files = preg_replace('~'. preg_quote($begin, ':'). '(\d+)'. preg_quote($end, '|'). '\s+~', $begin. $max_UNIX_val. $end. PHP_EOL, $files);

    file_put_contents($ALL_files_path, $files);
/*   Примерный вид строки в файле:  \DIR1\DIR2\...\fileName;0|25    */

$mess =  'Для файлов, имена которых содержатся в файле-перечне <br/><br/><b>'. $ALL_files_path. ',</b><br/><br/> обновлены метки времени UNIX. <br/><br/>Теперь <b>НЕОБХОДИМО</b>  запустить индексирование этих файлов (иначе поиск будет происходить НЕПРАВИЛЬНО). В результате индексирования будет создан/обновлен каталог <b>/'. basename($path_DIR_name). '</b> с содержащимися там каталогами, сообразно символам метафонов слов контента файлов сайта, а также файлами 1.txt.';

}else{
    // ...
}

echo $mess;


echo '<p>Затрачено времени: '.(microtime(true)- $t0).'</p>';


/*****************     ФУНКЦИИ     *************************/

// Используется рекурсия. Поэтому при достаточно большом числе файлов на сайте эта функция может дать сбой.
function look_dir1($path, $entry, &$dirs_ALL_Arr, &$path_done_Arr, $forbidden_FILE_extensions_Arr, $forbidden_dirs_Arr, $allowed_FILE_extensions_Arr, $ALL_files_path, &$i){

    if(in_array(basename($path), $forbidden_dirs_Arr)){ // Если последняя часть пути содержится в массиве запрещенных каталогов
        return $dirs_ALL_Arr;
    }

    chdir($path);

    if($handle = opendir($path)){
        while (false !== ($entry = readdir($handle))) {

            if (is_dir($entry)) { // Если каталог
                if (($entry == ".") || ($entry == "..")) {
                    continue;
                }
                $entry = realpath($entry);

                if(in_array(basename($entry), $forbidden_dirs_Arr)){ // Если последняя часть пути содержится в массиве запрещенных каталогов
                    continue;
                }

                if(in_array($entry, $path_done_Arr)){ // Если уже просматривали этот каталог (на всякий случай)
                    continue;
                }

                $dirs_ALL_Arr = DIRS_in_DIR($path, $forbidden_dirs_Arr, $dirs_ALL_Arr, $path_done_Arr); // Массив каталогов в текущем каталоге

                look_dir1($entry, $entry, $dirs_ALL_Arr, $path_done_Arr, $forbidden_FILE_extensions_Arr, $forbidden_dirs_Arr, $allowed_FILE_extensions_Arr, $ALL_files_path, $i);
            }
        }
        closedir($handle);
        chdir('..');
    }else{
        echo '<p>Каталог '. realpath($entry). ' не может быть открыт.</p>';
    }

return $dirs_ALL_Arr;
}


function DIRS_in_DIR($path, $forbidden_dirs_Arr, &$dirs_ALL_Arr, &$path_done_Arr){ // Возвращает:
    /* -1, если $path НЕ является каталогом,
     * массив каталогов в текущем каталоге (без .  и  ..).
     */
    if(is_dir($path)){ // Требуется ПОЛНЫЙ путь
        $path_Arr = scandir($path);
        $path_Arr = array_map(function ($name) use ($path){
            return realpath($path. '/'. $name);
        }, $path_Arr);
    }else{
        return -1;
    }

    $forbidden_dirs_Arr = array_merge($forbidden_dirs_Arr, array('.', '..'));

    $rez_Arr = array_filter($path_Arr, function ($name) use ($path, $forbidden_dirs_Arr){
        return is_dir(realpath( $name)) && !in_array(basename($name), $forbidden_dirs_Arr); // Только НЕ запрещенные каталоги включаем в массив
    });


    $rez_Arr = array_values($rez_Arr);
    $dirs_ALL_Arr = array_merge($rez_Arr, $dirs_ALL_Arr);
    $dirs_ALL_Arr = array_unique($dirs_ALL_Arr);

    $path_done_Arr[] = realpath($path);
    $path_done_Arr = array_unique($path_done_Arr);

    return array_values($dirs_ALL_Arr);
}


// Функция записывает относительные пути файлов, имеющихся на сайте, в файл (вместе с их уникальными индексами и метками времени UNIX)
/*  Здесь используются массивы. Эта функция работает к несколько раз медленнее, чем аналогичная ф-ция на регулярных выражениях  */
/*function save_FILES_NAMES1($files_Arr, &$files_all_Arr,  $entry, $ALL_files_path, &$i){
    if(sizeof($files_Arr)){ // Если в текущем каталоге есть файлы

        for($j=0; $j < sizeof($files_Arr); $j++){ // По каждому файлу из текущего каталога
set_time_limit(40);
            $file_entry = realpath($entry. '/'. $files_Arr[$j]);

$pos = strlen($_SERVER['DOCUMENT_ROOT']);
$entry_rel = substr($file_entry, $pos);

            $filemtime = filemtime($file_entry); // Время модификации файла
            
            for($i=0; $i < sizeof($files_all_Arr); $i++){ // Заменяем метку времени в строке из файла-перечня files.txt
                if(stripos($files_all_Arr[$i], $entry_rel) === 0){

                    $index_Arr = explode('|', $files_all_Arr[$i]);
                    $index = $index_Arr[1];

                    $files_all_Arr[$i] = $entry_rel. ';'. $filemtime. '|'. $index;
                    break;
                }
            }
        }
    }
return $files_all_Arr;
}*/

// Функция обновляет метки времени UNIX в массиве $files_all_Arr (только для файлов, которые есть в массиве $files_Arr)
function save_FILES_NAMES2($files_Arr, $files_all_Arr, $entry, &$max_UNIX_val){
/* $files_Arr     - массив имен файлов из выбранного каталога (за исключением .  и  ..)
   $files_all_Arr - массив индексируемых ВСЕХ файлов сайта (взят из files.txt)
   $entry         - выбранный каталог
   $max_UNIX_val  - максимальное значение меток UNIX среди файлов из $files_all_Arr
*/
    if(sizeof($files_Arr)){ // Если в текущем каталоге есть файлы

        for($j=0; $j < sizeof($files_Arr); $j++){ // По каждому файлу из выбранного каталога
set_time_limit(40);
            $file_entry = realpath($entry. '/'. $files_Arr[$j]);

$pos = strlen($_SERVER['DOCUMENT_ROOT']);
$entry_rel = substr($file_entry, $pos);

            $filemtime = filemtime($file_entry); // Время модификации файла

            if($filemtime > $max_UNIX_val){
                $max_UNIX_val = $filemtime;
            }

            $files_all_Arr = preg_replace('~[\n\r]+'. preg_quote($entry_rel, '\\'). ';\d+\|' .'~' ,  PHP_EOL. addslashes($entry_rel). ';'. $filemtime. '|', $files_all_Arr);
        }



    }
return $files_all_Arr;
}


// Используется рекурсия. Поэтому при достаточно большом числе файлов на сайте эта функция может дать сбой. Не нужно? +++
/*function look_dir($path, $entry, $forbidden_FILE_extensions_Arr, $forbidden_dirs_Arr, $allowed_FILE_extensions_Arr, $ALL_files_path, &$i){

    if(in_array(basename($path), $forbidden_dirs_Arr)){ // Если последняя часть пути содержится в массиве запрещенных каталогов
        return;
    }

    chdir($path);
    if($handle = opendir($path)){

        while (false !== ($entry = readdir($handle))) {

            if (is_dir($entry)) { // Если каталог
                if (($entry == ".") || ($entry == "..")) {
                    continue;
                }
                $entry = realpath($entry);

    if(in_array(basename($entry), $forbidden_dirs_Arr)){ // Если последняя часть пути содержится в массиве запрещенных каталогов
        continue;
    }

                $files_Arr = FILES_in_DIR($entry, $forbidden_FILE_extensions_Arr, $allowed_FILE_extensions_Arr); // Массив файлов в текущем каталоге (только файлы, без каталогов)
                save_FILES_NAMES($files_Arr, $entry, $ALL_files_path, $i); // Выводим (записываем в файл) имена файлов

                look_dir($entry, $entry, $forbidden_FILE_extensions_Arr, $forbidden_dirs_Arr, $allowed_FILE_extensions_Arr, $ALL_files_path, $i);
            }
        }
        closedir($handle);
        chdir('..');
    }else{
        echo 'Каталог '. realpath($entry). ' не может быть открыт.';
    }

}*/

//Функция записывает относительные пути файлов, имеющихся на сайте, в файл (вместе с их уникальными индексами и метками времени UNIX)
function save_FILES_NAMES($files_Arr, $entry, $ALL_files_path, &$i, $letters_Arr){
    if(sizeof($files_Arr)){ // Если в текущем каталоге есть файлы

        for($j=0; $j < sizeof($files_Arr); $j++){

            $file_entry = realpath($entry. '/'. $files_Arr[$j]);

$pos = strlen($_SERVER['DOCUMENT_ROOT']);
$entry_rel = substr($file_entry, $pos);
//    file_put_contents($ALL_files_path, $entry_rel. ';0' .'|'. $i++ . PHP_EOL, FILE_APPEND); // Записываем относительные пути к файлам в файл-перечень
            $file_index = base_conv_10_to_N($i++, $letters_Arr); // Преобразуем 10-чное число в N-ричный индекс

    file_put_contents($ALL_files_path, $entry_rel. ';0' .'|'. $file_index . PHP_EOL, FILE_APPEND); // Записываем относительные пути к файлам в файл-перечень
/*   Примерный вид строки в файле:  \DIR1\DIR2\...\fileName;0|2Fh    */
        }
    }
}


function FILES_in_DIR($path, $forbidden_FILE_extensions_Arr, $allowed_FILE_extensions_Arr){ // Возвращает:
    /* -1, если $path НЕ является каталогом,
     * массив файлов в текущем каталоге (только файлы, без каталогов).
     */
    if(is_dir($path)){ // Требуется ПОЛНЫЙ путь
        $path_Arr = scandir($path);
    }else{
        return -1;
    }

    $rez_Arr = array_filter($path_Arr, function ($name) use ($path){
        return !is_dir(realpath($path. '/'. $name)); // Каталоги НЕ включаем в массив
    });

// Оставляем в массиве файлов только те, расширения которых НЕ содержатся в списке (массиве) запрещенных файлов. Т.е. за исключением рисунков, видео, pdf и пр.
// Или же оставляем те, которые СОДЕРЖАТСЯ в списке (массиве) разрешенных расширений
    $rez_Arr = array_filter($rez_Arr, function ($file_name) use ($forbidden_FILE_extensions_Arr, $allowed_FILE_extensions_Arr){



        if(!!$allowed_FILE_extensions_Arr){ // Если не null, т.е. НУЖНО использовать массив разрешенных расширений файлов
            return in_array(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)), $allowed_FILE_extensions_Arr);
        }

        return !in_array(strtolower(pathinfo($file_name, PATHINFO_EXTENSION)), $forbidden_FILE_extensions_Arr);
    });
    $rez_Arr = array_values($rez_Arr);

    return array_values($rez_Arr);
}


/* 19. Функция выдает (однобайтовые) символы ASCII (для создания алфавита, для использования его в качестве единиц системы исчисления вместо 10-чного) */
function GET_some_ASCII(){
    $indexes_range_Arr = array('0-8', '11-12', '16-30', '35-34', '48-57', '65-90', '99-122', '129-255');
    $indexes_Arr = array();
    for($i=0; $i < sizeof($indexes_range_Arr); $i++){
        $tmp_Arr = explode('-', $indexes_range_Arr[$i]);

        for($j=$tmp_Arr[0]; $j <= $tmp_Arr[1]; $j++){
            $indexes_Arr[] = chr($j); // Массив однобайтовых символов ASCII, к-рые можно применять в качестве единиц системы исчисления
    //        echo chr($j).' -> '. $j;
        }
    }

return $indexes_Arr;
}




/* 20. Функция преобразовывает N-ричное число в 10-чное (обратное преобразование по отношению к функции base_conv_to_N())  */
function base_conv_N_to_10($str_N, $letters_Arr){

//    $letters_Arr = str_split($letters);
    $N_razr = sizeof($letters_Arr);

    $str_razr_Arr = str_split($str_N);
    $str_razr_Arr_size = sizeof($str_razr_Arr);

    $x_Arr = array_map(function ($el) use ($letters_Arr){
        return array_search($el, $letters_Arr);
    }, $str_razr_Arr);

    $num = 0;
    for($i=0; $i < $N_razr; $i++){
        $num += $x_Arr[$i]* pow($N_razr, $str_razr_Arr_size - $i - 1);
    }

return $num;
}

/* 21. Функция преобразовывает 10-чное число в N-ричное число  */
function base_conv_10_to_N($num, $letters_Arr){

    if(!is_int($num)){
        return array(-1, 'Ошибка: 1-й аргумент функции '. __FUNCTION__ . '() должен быть натуральным числом, но он задан равным '. $num);
    }

//    $letters_Arr = str_split($letters);
    $N_razr = sizeof($letters_Arr);

$n_max = 0;
$num_tmp = $num;

while (1){
    $num_tmp = $num_tmp / $N_razr;

    if($num_tmp < 1){
        break;
    }
    $n_max++;

    if($n_max > 10){
        return array(-1, 'Ошибка: слишком большое значение показателя степени при определении числа '. $num. ' в '. $N_razr. '-ричной системе исчисления.');
    }
}

    $num_int_div_Arr = array();
    $num_remainder = $num;
    for ($n=$n_max; $n >= 0; $n--){

        $N_razr__n = pow($N_razr, $n);
        $num_int_div_Arr[$n] = intdiv_1($num_remainder, $N_razr__n);

        if(is_array($num_int_div_Arr[$n])){
            return $num_int_div_Arr[$n];
        }

        $num_remainder -= $num_int_div_Arr[$n] * $N_razr__n;
    }

    $num_razr_Arr = array_map(function ($el) use ($letters_Arr) {
        return $letters_Arr[$el];
    }, $num_int_div_Arr);

    $str = implode('', $num_razr_Arr);

return $str;
}

/* 22. Функция целочисленного деления  */
function intdiv_1($a, $b){
    if($b === 0){
        return array(-1, 'Ошибка: в функции целочисленного деления делитель не может быть равным 0.');
    }
    if(!is_int($a) || !is_int($b)){
        return array(-1, 'Ошибка: в функции '. __FUNCTION__ . '() '. ' один из аргументов имеент нецелое значение. ');
    }
return ($a - $a % $b) / $b;
}