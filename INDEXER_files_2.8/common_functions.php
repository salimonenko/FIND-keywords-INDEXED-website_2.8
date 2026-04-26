<?php
/* 1. Получаем метафоны */
function do_metaphone1($word, $metaphone_len){

    if(strlen($word) < $metaphone_len || preg_match('/[\d\+]/', $word)){ // Если слово короткое или содержит цифру или +, то будем искать точное соответствие (в транслите)
        return strtolower($word);
    }

    $word_metaphone = metaphone($word, $metaphone_len);

    if(strlen($word_metaphone) < ($metaphone_len-1)){ // Если функция metaphone дала слишком короткую строку-код
        return strtolower($word);
    }else{
        return strtolower($word_metaphone);
    }

}


/* 2. Транслит (обычный, НЕ псевдо) */
function translit1($value)
{
    $converter = array(
        'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
        'е' => 'e',    'ё' => 'e',    'ж' => 'zh',   'з' => 'z',    'и' => 'i',
        'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
        'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
        'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'c',    'ч' => 'ch',
        'ш' => 'sh',   'щ' => 'sch',  'ь' => '',     'ы' => 'y',    'ъ' => '',
        'э' => 'e',    'ю' => 'yu',   'я' => 'ya',

        'А' => 'A',    'Б' => 'B',    'В' => 'V',    'Г' => 'G',    'Д' => 'D',
        'Е' => 'E',    'Ё' => 'E',    'Ж' => 'Zh',   'З' => 'Z',    'И' => 'I',
        'Й' => 'Y',    'К' => 'K',    'Л' => 'L',    'М' => 'M',    'Н' => 'N',
        'О' => 'O',    'П' => 'P',    'Р' => 'R',    'С' => 'S',    'Т' => 'T',
        'У' => 'U',    'Ф' => 'F',    'Х' => 'H',    'Ц' => 'C',    'Ч' => 'Ch',
        'Ш' => 'Sh',   'Щ' => 'Sch',  'Ь' => '',     'Ы' => 'Y',    'Ъ' => '',
        'Э' => 'E',    'Ю' => 'Yu',   'Я' => 'Ya',
    );

    $value = strtr($value, $converter);
    return $value;
}

/* 3. Определение кодировки */
function check_enc($text_html, $enc_Arr){ // В РНР 5.3 работает отлично. А в РНР 8.0 могут быть сбои, если входная строка будет в иной кодировке
// В массиве кодировок $enc_Arr ПЕРВОЙ должна идти utf-8
    $true_encoding = '';

    foreach ($enc_Arr as $encoding){
        if(mb_check_encoding($text_html, $encoding)){
            $true_encoding =  $encoding;
            break;
        }
    }

    return $true_encoding;
}

/* 4. Итоговое сообщение об ошибках */
// Функция окончательно проверяет ошибки
function check_ERRORS($mess){
// На всякий случай, окончательно делаем контроль ошибок
// *************    КОНТРОЛЬ ОШИБОК    (Начало)*****************************************
         if((error_get_last() != '') || (is_array(error_get_last()) && (error_get_last() != array()) )){
             print_r(error_get_last()); // Выводим клиенту, чтобы можно было посмотреть в ответах сервера

             if($mess){ // Это для ошибок, сообщения для которых вручную заданы в той или иной программе
                file_put_contents(PATH_FILE_NAMES_ERROR, $mess . PHP_EOL , FILE_APPEND);
             }

             save_ERROR_mes(); // Если была ошибка, сохраняем в файл также системное сообщение о ней в файл-лог ошибок

             return '<p class="error_mes">'. $mess. '</p>'. implode(PHP_EOL, error_get_last());
         }else{
             return '';
         }
// *************    /КОНТРОЛЬ ОШИБОК    (Конец)*****************************************
}

/* 5. Удаление НЕПУСТОГО каталога. РАБОТАЕТ ПЛОХО (может удалить не все каталоги, если их - много). Но, если сделать на итераторах, будет работать еще хуже (очень медленно)  */
function rrmdir($src, $DO_working_flag_FILE ) {

    set_time_limit(40);

/* Проверяем, присутствует ли флаговый файл. Если да, то делаем следующую рекурсии. Если нет - прекращаем */
    if(!file_exists($DO_working_flag_FILE)){
        return 'stop';
    }

    $dir = opendir($src);

    while(false !== ($file = readdir($dir))) {
        if(($file != '.' ) && ( $file != '..' )) {

            $full = $src . '/' . $file;
            if(is_dir($full)) {

           /*     if(!file_exists($DO_working_flag_FILE)){
                    return false;
                }*/

                $rez = @rrmdir($full, $DO_working_flag_FILE);
                $mess = 'каталога ';
            }else{
                $rez = @unlink($full);
                $mess = 'файла ';
            }
            if(!$rez){
                echo 'Ошибка при удалении '. $mess. $full;
                print_r(error_get_last());
                die();
            }
        }
    }
    closedir($dir);

    if(!file_exists($DO_working_flag_FILE)){
        return 'stop';
    }

    rmdir($src);
return true;
}
/*
function rrmdir($src) {
    $dir = opendir($src);
    $src = realpath($src);


    $dirs_Arr = array_filter(scandir($src), function ($el) {
       if(is_file($el)){
           unlink($el);
            return false;
       }else{
           if($el == '.' || $el == '..'){
               return false;
           }

           return true;
       }
    });

    if(sizeof($dirs_Arr) > 0){
        $src =  realpath($src. '/'. $dirs_Arr[0]);
    }else{
        rmdir($src);
    }

}*/




/* 6. Функция определяет кодировку файла с перечнем файлов сайта files.txt и получает массив, состоящий из имен этих файлов */
function get_files_Arr($enc_Arr){
    $str = file_get_contents(PATH_FILE_NAMES_ALL_FILES);

$ENC_FILE_names_all_files = strtolower(check_enc($str, $enc_Arr));

    if(!$ENC_FILE_names_all_files){
        $mess = ' Не удалось определить кодировку файла (в функции '. __FUNCTION__ . '())  ' . PATH_FILE_NAMES_ALL_FILES;
        file_put_contents(PATH_FILE_NAMES_ERROR, $mess. ' '. date("d.m.Y - H:m:s"). PHP_EOL , FILE_APPEND);
    $mess .= '<p class="error_mes">'. $mess. '</p>';

return array(-1, $mess, null);
    }

$ALL_files_Arr_tmp = explode(PHP_EOL, $str); // Вместо функции file(), т.к. она, вроде бы, работает медленнее
unset($str);

$ALL_files_Arr = array(); // Массив относительных имен файлов
for($i=0; $i < sizeof($ALL_files_Arr_tmp); $i++){
    $elem = trim($ALL_files_Arr_tmp[$i]);

    if($elem){
        $pos = strpos($elem, '|') + 1;
        $key = substr($elem, $pos);
        $ALL_files_Arr[$key] = substr($elem, 0, $pos - 1); // Элемент массива вида: 3 => filename
    }
}
    if(isset($ALL_files_Arr['index'])){
        $max_UNIX_Arr = explode(':', $ALL_files_Arr['index']);
        $max_UNIX_saved = $max_UNIX_Arr[1];
        unset($ALL_files_Arr['index']);
    }else{
        $max_UNIX_saved = null;
    }

return array($ALL_files_Arr, $ENC_FILE_names_all_files, $max_UNIX_saved);
}


/* 7. Функция индексирует файл сайта (добавляя его индекс в индексный файл) ИЛИ добавляет признак присутствия слова в словаре в индексный файл  */
function LAST_met_2_index($keyword_metaph, $body_Arr_k_count, $dic_word_SUFF, $file_name, $path_DIR_name, $COMMON_file_name, $number, $ALL_files_Arr, $JS_manage_mes, $dic_word_WORD, $str_COMMON_FILE, $path_common_index_name, $min_index_FILE_len){

    $LAST_met_path_Arr = create_path_index_file($keyword_metaph, $path_DIR_name, false); // На данном этапе каталог для файла 1.txt не создаем
    $index_FILE = $LAST_met_path_Arr[0];
    $LAST_met_2 = $LAST_met_path_Arr[1];
    $index_FILE_rel = trim($LAST_met_path_Arr[2]);

$dic_word_WORD_adding = ''; //  str_replace('/', '', translit1("\n+".trim($dic_word_WORD)));
$reg1 = reg_find_endexes_metaph($index_FILE_rel);

            if($file_name === true && $number === ''){ // Если вставляется признак присутствия этого слова в файле-словаре ru.dic ("1" или суффикс, при его наличии)
                $delim = ':'. $dic_word_SUFF. '|';
                $to_SAVE = $LAST_met_2. $delim. ';'; // Строка вида ag:1|; или  ag:/HB|; (т.к. проводилось индексирование файла-СЛОВАРЯ)
            }else{ // Если индексируется содержимое файлов сайта
                $delim = '|;';
                $to_SAVE = $LAST_met_2. $delim. $number. '*'. $body_Arr_k_count.';'; // Строка вида ag|;56*3;
            }

        if(file_exists($index_FILE)){ // Если отдельный индексный файл существует, то работаем с ним, а не с общим индексным файлом
            if(!is_writable($index_FILE)){ // Если файл существует, но НЕ доступен для записи (значит, что-то пошло не так)
                $mess = 'Ошибка: не получилось записать число-индекс в файл '.$index_FILE. '. Т.к. этот файл недоступен для записи.';
                echo '<p class="error_mes">'. $mess. '</p>'. $JS_manage_mes;
                file_put_contents(PATH_FILE_NAMES_ERROR, $file_name. '|'. array_search($file_name, $ALL_files_Arr). '|'. $mess. ' '. date("d.m.Y - H:m:s"). PHP_EOL , FILE_APPEND);

                return $str_COMMON_FILE;
            }
        }else{ // Если файл не существует, то, возможно, потребуется его создать (если размер записи для текущего метафона выше заданного размера)
            $flag_SAVE = true;

        }

    $to_SAVE = what_to_SAVE($keyword_metaph, $path_DIR_name, $to_SAVE, $file_name, $LAST_met_2, $number, $delim, $index_FILE_rel, $body_Arr_k_count, $path_common_index_name);

        if($to_SAVE){ // Если НЕ null

            $to_SAVE_COMMON = $dic_word_WORD_adding. "=". $index_FILE_rel."\n". $to_SAVE . "\n+";

            // Если еще нет отдельного инд. файла и размер информации индексов для текущего метафона ниже заданного значения, то добавляем его в общий инд. файл (или обновляем его). Если же превысил, то удаляем эту информацию из общего инд. файла и сохраняем ее в отдельном инд. файле
            if(!is_file($index_FILE)){ // Если еще нет отдельного инд. файла
                if((strlen($to_SAVE_COMMON) < $min_index_FILE_len)  ){ // Если размер вставки для замены не превышает заданную величину (например, 2000 Байт)
                    if(preg_match($reg1, $str_COMMON_FILE, $matches)){ // Если инф. о текущем метафоне уже есть в общем инд. файле, то обновляем ее
                        $str_COMMON_FILE = preg_replace($reg1, $to_SAVE_COMMON, $str_COMMON_FILE); // Обновляем в общем инд. файле информацию о текущем метафоне
                    }else{ // Если такой инф. еще нет, до добавляем ее
                        $str_COMMON_FILE .= $to_SAVE_COMMON;
                    }
                    file_put_contents($COMMON_file_name, $str_COMMON_FILE); // Обновляем содержимое общего индексного файла

                }else{ // Если превышает, тогда создаем отдельный индексный файл, а из общего файла индексную информацию о метафоне удаляем
                    create_path_index_file($keyword_metaph, $path_DIR_name, true); // Создаем каталог (если его еще нет) для отдельного инд. файла 1.txt
                    file_put_contents($index_FILE, $to_SAVE . "\n"); // Сохраняем (обновляем) этот инд. файл

                    $str_COMMON_FILE = preg_replace($reg1, '', $str_COMMON_FILE); // Удаляем из общего инд. файла информацию о текущем метафоне
                    file_put_contents($COMMON_file_name, $str_COMMON_FILE); // Обновляем содержимое общего индексного файла
                }

            }else{ // Если ЕСТЬ отдельный инд. файл, то работаем уже только с ним, общий инд. файл не трогаем ради текущего метафона
                file_put_contents($index_FILE, $to_SAVE . "\n");
            }
        }
// В итоге относительный путь к этому файлу будет примерно таким:  /metaphones/s/h/1.txt (для метафона shag). Буквы ag будут содержаться в одной из строк файла
// В этом файле будут содержаться индексные номера тех файлов (из files.txt), в которых метафон данного слова содержится хотя бы 1 раз

return $str_COMMON_FILE;
}


// Функция определяет информацию (одна или неск. строчек) соответствующую данному метафону; к-рую нужно вставить в индексный (общий или отдельный) файл
function what_to_SAVE($keyword_metaph, $path_DIR_name, $to_SAVE, $file_name, $LAST_met_2, $number, $delim, $index_FILE_rel, $body_Arr_k_count, $path_common_index_name){

    $index_FILE = $path_DIR_name. $index_FILE_rel;

    // Получаем индексную информ. для данного метафона - из отдельного или общего инд. файла
    $index_FILE_str_Arr = get_metaph_indexes($keyword_metaph, 0, $path_DIR_name, $path_common_index_name, $index_FILE_rel, array());
    $index_FILE_str = $index_FILE_str_Arr[0];


        $reg = "~(?:^|\n)". $LAST_met_2. '(([^\n\r]*;('. $number. '\*?(\d+)?);))~'; // Ищем подстроку типа ag...;56; или ag...;56*3;

        $flag_SAVE = false; // Флаг, нужно ли сохранять этот массив

            if(preg_match($reg, $index_FILE_str, $matches)){
//print_r($matches);
                $index = $matches[3]; // Что-то типа  ;56*3;
// 1. Вначале удаляем индекс из соотв. строки
                $index_FILE_str = str_replace(';'. $index. ';', ';', $index_FILE_str);
            }

            $index_FILE_Arr = explode("\n", $index_FILE_str);

            $index_FILE_Arr = array_filter($index_FILE_Arr, function ($el){
                return $el != '';
            });
            for($z=0; $z < sizeof($index_FILE_Arr); $z++){ // По каждой строчке индексного файла

                $elem = trim($index_FILE_Arr[$z]);
                if(substr($elem, 0, 2) === $LAST_met_2){ // Если в начале элемента массива есть 2 символа типа ag

                    if($file_name === true && $number === ''){ // Если вставляется признак "1" - присутствия этого слова в файле-словаре ru.dic
                        if(substr($elem, 0, 5) !== $LAST_met_2. $delim){ // Если конец метафона есть, но признака присутствия еще нет

                            $index_FILE_Arr[$z] = trim(preg_replace('/^'. $LAST_met_2. '[^\\\n;]*/', $LAST_met_2. $delim, $index_FILE_Arr[$z]));
                            $flag_SAVE = true;
                        }
                            break; // Только в случае добавления признака "1" присутствия слова в файле-словаре (с учетом метафонизации)

                    }else{
                            if(strstr($elem, ';'. $number. ';') !== false){ // Если в том же элементе есть подстрока вида ;56; (дополнительная проверка на всякий случай, т.к. выше эта подстрока была УДАЛЕНА)
                                break; //Если такая подстрока уже есть, значит, ее уже не нужно вставлять (если делается ИНДЕКСИРОВАНИЕ, а не вставка признака "1")
                            }else{ // Если еще нет, то добавляем. Будет что-то типа ag|;56*3;36;49*5; ... (или ag:1|;56*3;36;49*5; )
                                if($body_Arr_k_count === 1){
                                    $adding = ''; // Если число вхождений слов равно 1
                                }else{
                                    $adding = '*'. $body_Arr_k_count; // Если число вхождений более 1, то добавляем это число
                                }


                                $index_FILE_Arr[$z] = $elem. $number. $adding. ';';
                                $flag_SAVE = true;
                                    break;
                            }
                    }
                }
            }
                if($z === sizeof($index_FILE_Arr)){
                // Если дошли досюда, т.е. НЕТ искомой подстроки типа ag
                    $index_FILE_Arr[] = $to_SAVE; // то добавляем в массив новый элемент
                        $flag_SAVE = true;
                }

                if($flag_SAVE){
                    $to_SAVE = implode("\n", $index_FILE_Arr);
                }

return $flag_SAVE ? $to_SAVE : null;
}







// Пока не используется +++
function cmp($a, $b){
    $possible_indexes_Arr = GET_some_ASCII1();

    $a_Arr = str_split($a);
    $b_Arr = str_split($b);

    $a_Arr = array_map(function ($el) use ($possible_indexes_Arr){
        $key = array_search($el, $possible_indexes_Arr);

    }, $a_Arr);



    return strcmp($a, $b);

}

/* 20. Функция выдает массив некоторых ASCII-символов  */
function GET_some_ASCII1(){
    return str_split('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
}

/* 8. Функция создает (или получает, если есть) путь к индексному файлу с именем вида $path_DIR_name/b/s/a/1.txt  */
function create_path_index_file($keyword_metaph, $path_DIR_name, $flag_mkdir){
    $path_DIR_name_TMP_rel = '';
    $path_DIR_name_TMP = $path_DIR_name;
    for($j=0; $j < strlen($keyword_metaph)-2; $j++){ // По каждому отдельному символу данного слова, кроме предпоследнего и последнего символов
        $DIR_name_1 = substr($keyword_metaph, $j, 1); // Имена создаваемых каталогов будут состоять из 1 символа (a, b, c, d или т.п.)

        $path_DIR_name_TMP = $path_DIR_name_TMP. '/'. $DIR_name_1;
        $path_DIR_name_TMP_rel = $path_DIR_name_TMP_rel . '/'. $DIR_name_1;
        if(!is_dir($path_DIR_name_TMP) && $flag_mkdir){
            mkdir($path_DIR_name_TMP);
        }
    }
    $index_FILE = $path_DIR_name_TMP.'/1.txt';
    $index_FILE_rel = $path_DIR_name_TMP_rel.'/1.txt';
    $LAST_met_2 = substr($keyword_metaph, $j, 2); // Последние 2 символа метафона


return array($index_FILE, $LAST_met_2, $index_FILE_rel);
}

/* 9. Функция проверяет корректность полученного логического выражения (перед последующей оценкой при помощи eval)  */
function check_keywords($keywords_Arr, $special_symb_Arr, $message_to_user, $bool_val){
    $bool_expression = implode('', array_map(function ($el) use ($special_symb_Arr, $bool_val){
    if(!in_array($el, $special_symb_Arr, true)){
        return $bool_val;
    }else{
        return $el;
    }
}, $keywords_Arr));

$rez_Arr = eval_keywords($bool_expression, $message_to_user);
return $rez_Arr;
}

/* 10. Функция оценивает логическое выражение и выдает результат: true или false  */
function eval_keywords($bool_expression, $message_to_user){
    $bool_expression_REZ = 0; // true, если есть совпадение с выражением для искомых искомых слов; false - если нет.

    $str_code = "\$bool_expression_REZ = ". $bool_expression;
    @eval($str_code. "|| 1". ";"); // Для проверки корректности выражения $str_code. Если оно верно, результат eval() даст заведомо 1 (true)

    if(!!$bool_expression_REZ){
        eval($str_code. ";"); // Если ошибки не было, получаем фактическое значение

        return array(null, !!$bool_expression_REZ);

    }else{ // Значит, возникла ошибка в выражении для eval()
        return array(-1, $message_to_user);
    }
}

/* 11. Функция получает массив индексов (из соответствующих индексных файлов) для каждого искомого (ключевого) слова из массива $keywords_Arr  */
function get_indexes_Arr($keywords_Arr, $special_symb_Arr, $path_DIR_name, $keywords_FALSE_Arr, $message_to_user, $path_common_index_name){
/* Поиск делается:
    1. Вначале в индексном файле вида /metaphones/m/o/r/a/l/n/1.txt. Это - более быстрый способ. Если такой файл есть.
    2. Потом в ОБЩЕМ индексном файле вида /metaphones/common_m.txt. Это - более долгий способ (теоретически).
 */
    $keyword_indexes_num_Arr = array(); // Для начала - пустой. Потом туда будут добавлены индексы для проиндексированных искомых кл. слов
/* Массив вхождений каждого индекса:
 Array(
        Слово => (Array( Индекс => ЧислоВхождений))
      )
*/
    for($i=0; $i < sizeof($keywords_Arr); $i++){ // По массиву искомых кл. слов
        if(in_array($keywords_Arr[$i], $special_symb_Arr, true)){
            continue; // символы типа && || (  ), а также 1 - не ищем
        }

// 1. Создаем путь к файлу 1.txt из каталога metaphones
        $str_to_DIRS = substr($keywords_Arr[$i], 0, -2);
        $str_to_FILE = substr($keywords_Arr[$i], -2);

        $path_rel = '/' .implode('/', str_split($str_to_DIRS)). '/1.txt';
        $path = realpath($path_DIR_name. $path_rel); // абсолютный путь

        $str_from_file_Arr = get_metaph_indexes($keywords_Arr[$i], $i, $path_DIR_name, $path_common_index_name, $path_rel, $keywords_FALSE_Arr);
            $str_from_file = $str_from_file_Arr[0];


        if($str_from_file === null){
            $keywords_FALSE_Arr = $str_from_file_Arr[1];
            continue;
        }

        $keywords_FALSE_Arr = $str_from_file_Arr[1];

/* 2. Читаем отдельный (если он есть) или общий индексный файл, извлекаем оттуда запись для метафона. Например, для метафона absnsk будет извлечена запись вида:
       /a/b/s/n/1.txt (это - или имя отд. индексного файла, или первая строчка записи из общего инд. файла)
       sk:/A|;2*3;g5;   <-  целевая информация
       sf:1|;f;
       sm:1|;52;
*/
        $keyword_indexes_num_Arr_Arr = keyword_indexes_num($str_from_file, $str_to_FILE, $path, $i, $keywords_Arr[$i], $keywords_FALSE_Arr, $special_symb_Arr, $message_to_user, $keyword_indexes_num_Arr); // Находим массив индексов (в него будут ДОБАВЛЯТЬСЯ данные по каждому искомому кл. слову, если они есть)

            $keyword_indexes_num_Arr = $keyword_indexes_num_Arr_Arr[0];
            $keywords_FALSE_Arr = $keyword_indexes_num_Arr_Arr[1];
    }

return array($keyword_indexes_num_Arr);
}


// Функция читает или отдельный индексный файл (если он есть), или общий индексный файл. И извлекает оттуда информацию-запись для метафона
function get_metaph_indexes($keyword, $i, $path_DIR_name, $path_common_index_name, $index_FILE_rel, $keywords_FALSE_Arr){
/*  $keyword - метафон
    $i - индекс элемента массива $keywords_FALSE_Arr
    $path_DIR_name - путь к каталогу /metaphones
    $path_common_index_name - Начальная часть имени общего индексного файла
    $index_FILE_rel - относит. путь к отдельному индексному файлу
    $keywords_FALSE_Arr - массив компонентов логич. выражения, в котором

Для метафона   absn (БЕЗ 2-х последних символов)
Будет что-то типа: Из общего инд. файла:               Из отд. инд. файла (будет прочитан ВЕСЬ файл):
                     /a/b/s/n/1.txt
                     sk:/A|;                             sk:/A|;
                     sf:1|;                              sf:1|;
                     sm:1|;                              sm:1|;
*/

    $path = $path_DIR_name. $index_FILE_rel;

    if(!file_exists($path)){ // Если такого (отдельного) индексного файла нет, значит, такого метафона нет в отдельных индексных файлах
// Но, может, он есть в общих индексных файлах вида /metaphones/common_b.txt  (?). Буква b - это ПЕРВЫЙ символ метафона
            $first_letter = substr($keyword, 0, 1);
            $COMMON_file_name = $path_DIR_name. $path_common_index_name. $first_letter. '.txt';

            if(file_exists($COMMON_file_name)){ // Если общий файл существует,
/* то находим в нем содержимое, относящееся к искомому метафону.
Оно будет вида:     =/a/b/s/n/1.txt
                    sk:/A|;
                    sf:1|;
                    sm:1|;
                    +
*/
                $str = file_get_contents($COMMON_file_name);

                if(preg_match(reg_find_endexes_metaph($index_FILE_rel), $str, $str_from_file_Arr)){ // Если есть содержимое, соотв. искомому метафону
                    // Предполагается, что такое содержимое содержится не более ОДНОГО раза в этом файле (так д.б.)
                    $str_from_file = $str_from_file_Arr[1];
                }else{ // Если такого содержимого нет, значит, такой метафон НЕ индексировался (т.е. искомое слово не найдено)
                    $keywords_FALSE_Arr[$i] = 0;
return array(null, $keywords_FALSE_Arr);
                    //continue;
                }

            }else{// Если уж и общий файл не существует, значит, такой метафон не был проиндексирован, его точно нет в индексных файлах
                $keywords_FALSE_Arr[$i] = 0;
return array(null, $keywords_FALSE_Arr);
                //continue;
            }

        }else{ // Если такой (отдельный) индексный файл есть
            $str_from_file = file_get_contents($path); // Берем его содержимое
        }

return array($str_from_file, $keywords_FALSE_Arr);
}

// 12. Функция получает массив индексов (из конкретного индексного файла) для конкретного искомого кл. слова
function keyword_indexes_num($str_from_file, $str_to_FILE, $path, $i, $keyword, $keywords_FALSE_Arr, $special_symb_Arr, $message_to_user, $keyword_indexes_num_Arr){

    $file_Arr = explode("\n", $str_from_file);

    // 1. Берем только тот элемент массива, который совпадает с 2-мя последними символами метафона
    $elem_Arr = array_filter($file_Arr, function ($el) use ($str_to_FILE){
        return substr($el, 0, 2) === $str_to_FILE;
    });

if(sizeof($elem_Arr) > 1){
    die('<p class="error_mes">Похоже, ранее произошла ошибка индексирования: в файле '. $path. ' присутствуе БОЛЕЕ ОДНОЙ строки, начинающейся на "'. $str_to_FILE.'. А должно быть НЕ БОЛЕЕ одной строки. Следует исправить программу, при помощи которой ранее производилось индексирование файлов сайта.</p>');
}elseif(sizeof($elem_Arr) === 0){ // Значит, нет индексов, соответствующих данному метафону
                $keywords_FALSE_Arr[$i] = 0;
            }else{ // Если в индексном файле ровно 1 такая строчка
                $elem_Arr = array_values($elem_Arr); // Чтобы начальный индекс массива стал равным 0

                $str = substr($elem_Arr[0], strpos($elem_Arr[0], '|') + 1); // Пропускаем начало строки с последними символами метафона ( типа yy:/AS| )
                $tmp_Arr = explode(';', $str);


                for($j=0; $j < sizeof($tmp_Arr); $j++){
                    $tmp1 = explode('*', $tmp_Arr[$j]);
                    $index = $tmp1[0];

                    if($index !== ''){
                        if(sizeof($tmp1) === 1){
                            $keyword_indexes_num_Arr[$keyword][$index] = 1;
                        }elseif(sizeof($tmp1) > 1){
                            $keyword_indexes_num_Arr[$keyword][$index] = $tmp1[1];
                        }
                    }
                }
            }

    // 2. И сразу проверяем, а вдруг при других метафонах, (пока) равных 0, логическое выражение уже будет равно true (это значит, что оно удовлетворяется и дальше можно не искать). Актуально для сложных логических выражений
            $rez_Arr = check_keywords($keywords_FALSE_Arr, $special_symb_Arr, $message_to_user, 0);

            if($rez_Arr[0] === -1){
                die('<p class="error_mes">Ошибка: выражение с искомыми словами составлено некорректно, функция eval() не может его оценить. Проблема возникла на слове '. $keyword. '</p>');
            }
            if($rez_Arr[1]){ // Если логическое выраж. уже равно true, значит, оно удовлетворяется и дальнейший поиск можно не делать (чтобы снизить время поиска - актуально для сложных и больших логических выражений)
                // Доделать todo +++
            }


return array($keyword_indexes_num_Arr, $keywords_FALSE_Arr);
}

/* 13. Функция сохраняем макс. метку времени UNIX в файл-перечень files.txt  */
function save_time_UNIX_MAX($str_UNIX_begin, $time_UNIX_i_MAX, $str_UNIX_end){
    $files = file_get_contents(PATH_FILE_NAMES_ALL_FILES);

// В 1-ю строку файла files.txt устанавливаем максимальную метку UNIX среди всех файлов, имена которых присутствуют в files.txt
    $files = preg_replace('~'. preg_quote($str_UNIX_begin, ':'). '(\d+)'. preg_quote($str_UNIX_end, '|'). '\s+~', $str_UNIX_begin. $time_UNIX_i_MAX. $str_UNIX_end. PHP_EOL, $files);

    file_put_contents(PATH_FILE_NAMES_ALL_FILES, $files);
}

// 14. Функция сохраняет сообщение о последней ошибке в файл-лог ошибок
function save_ERROR_mes(){
    file_put_contents(PATH_FILE_NAMES_ERROR, 'Ошибка - '. date("d.m.Y - H:m:s") . PHP_EOL , FILE_APPEND);

    array_map(function ($el) { // Сохраняем в файл также системное сообщение об ошибке построчно
        $str_to_out = array_search($el, error_get_last()). ' => '. $el;
        file_put_contents(PATH_FILE_NAMES_ERROR, $str_to_out. PHP_EOL , FILE_APPEND);
    }, error_get_last());
}


// 15. После окончания работы в случае ошибки сообщаем об этом (актуально, если будет Fatal error)
register_shutdown_function(function () {
// 1. При использовании SSE (событий сервера). Т.е. если был (будет) отправлен заголовок text/event-stream
    $text_event_stream = was_header('Content-Type', 'text/event-stream');

    if($text_event_stream === 1){
        require_once __DIR__ . '/sendMsg.php'; // Вывод результатов событий сервера
        if(check_ERRORS('')){ // Если были ошибки
            sendMsg(time(), '<p class="error_mes" style="display: block">Ошибка: </p>', false);

            array_map(function ($el) { // Выводим клиенту сообщение об ошибке при использовании SSE
                $str_to_out = array_search($el, error_get_last()). ' => '. $el;
                sendMsg(time(), '<p class="error_mes" style="display: block">'. $str_to_out. '</p>', false);
            }, error_get_last());

        }
    // Если невозможно определить, работают ли SSE (установлен ли заголовок Content-Type: text/event-stream)
    }elseif($text_event_stream === -1){
        save_ERROR_mes();  // Сохраняем в файл также системное сообщение об ошибке в файл-лог ошибок
        echo 'Ошибка в функции was_header() - '. __FUNCTION__ . ', стр.'. __LINE__ ; // Выводим клиенту хоть какое-то сообщение об ошибке
    }

});


/* 16. Функции делают псевдологические операции с числами. Могут складывать их или находить значение специальной функции (через eval).
    Для number1 || number2  ->     number_OR_F(number1, number2)
    Для number1 && number2  ->     number_I_F(number1, number2)
*/
// 16.1. Функция для выполнения ранжирования для псевдологического условия И ( && )
function number_I_F($number1, $number2){
/*                              |$number1 - $number2|
 *   min($number1, $number2) + -----------------------
 *                             max($number1, $number2)
 */
    if(!is_numeric($number1) || !is_numeric($number2)){
        return -1;
    }
    if(!$number1 || !$number2){
        return 0;
    }

return min($number1, $number2) + abs($number1 - $number2) / max($number1, $number2);
}
// 16.2. Функция суммирования. Определяет ранг для операции ИЛИ ( || )
function number_OR_F($number1, $number2){
    if(!is_numeric($number1) || !is_numeric($number2)){
        return -1;
    }

return $number1 + $number2;
}

// 17. Функция ищет пересечение массивов индексов. Для найденного пересечения определяет ранг каждого индекса по формуле, задаваемой при помощи ф-ции  number_I_F()
function array_inters__ect($b1, $b2){
/* $b1 = Array(              $b2 = Array(
               [33] => 1                 [33] => 6
               [99] => 3                 [76] => 1
               [103] => 1                [93] => 2
               [108] => 4                [108] => 2
              )                          [127] => 1
                                         [216] => 2
                                        )
*/
    $range_Arr = array();


    if(sizeof($b1) === 1 && $b1[0] === 1){ // Если вместо кл. искомого слова (например, для очень короткого) было установлено 1
        return $b2; // Просто возвращаем исходный массив: индекс => ранг
    }elseif (sizeof($b2) === 1 && $b2[0] === 1){ // Если вместо кл. искомого слова (например, для очень короткого) было установлено 1
        return $b1;
    }else{
        $indexes_Arr = array_keys(array_intersect_key($b1, $b2)); // Уникальные (содержащиеся в пересечении) ключи массивов
    }


// Собираем ранги для уникальных ключей
    for($i=0; $i < sizeof($indexes_Arr); $i++){ // По каждому уникальному индексу, присутствующему в пересечении
        $ind1 = $b1[$indexes_Arr[$i]];
        $ind2 = $b2[$indexes_Arr[$i]];
        $range_Arr[$indexes_Arr[$i]] = number_I_F($ind1, $ind2);
    }
    arsort($range_Arr); // Элементы с высокими рангами будет первыми

return $range_Arr;
}

// 18. Функция ищет объединение массивов индексов. Для найденного объединения определяет ранг каждого индекса по формуле, задаваемой при помощи ф-ции  number_OR_F()
function array_mer__ge($b1, $b2){
/* $b1 = Array(              $b2 = Array(
               [33] => 1                 [33] => 6
               [99] => 3                 [76] => 1
               [103] => 1                [93] => 2
               [108] => 4                [108] => 2
              )                          [127] => 1
                                         [216] => 2
                                        )
*/
    $range_Arr = array();

    $indexes = array_keys($b1);
    $indexes = array_values(array_merge(array_keys($b2), $indexes));
// Собираем ранги для уникальных ключей
    for($i=0; $i < sizeof($indexes); $i++){ // По каждому уникальному индексу, присутствующему в объединении

        if(isset($b1[$indexes[$i]])){
            $range_Arr[$indexes[$i]] = $b1[$indexes[$i]];
        }
        if(isset($b2[$indexes[$i]])){
            $range_Arr[$indexes[$i]] = $b2[$indexes[$i]];;
        }
        if(isset($b1[$indexes[$i]]) && isset($b2[$indexes[$i]])){
            $range_Arr[$indexes[$i]] = number_OR_F($b1[$indexes[$i]], $b2[$indexes[$i]]);
        }
    }
    arsort($range_Arr); // Элементы с высокими рангами будет первыми

return $range_Arr;
}

// 19. Функция проверяет, следует ли продолжать итерацию цикла (например, цикла индексирования файлов)
function must_continue($mess_Arr, $DO_working_flag_FILE, $flag_is_indexing, $str_UNIX_begin, $time_UNIX_i_MAX, $str_UNIX_end){
/*  Используются ДВА способа, для надежности: путем проверки наличия флагового файла, а также путем проверки, не прервано ли соединение со стороны клиента  */
// 19.1.  /* Проверяем, присутствует ли флаговый файл. Если да, то делаем следующую итерацию. Если нет - прекращаем цикл */
    $flag = true;

    if(!file_exists($DO_working_flag_FILE)){

         if($flag_is_indexing){ // Если индексирование делалось, но было прервано
// Перед прерыванием сохраняем макс. метку времени UNIX в файл-перечень files.txt
             if($str_UNIX_begin && $time_UNIX_i_MAX && $str_UNIX_end){ // Если заданы
                save_time_UNIX_MAX($str_UNIX_begin, $time_UNIX_i_MAX, $str_UNIX_end);
             }

             $mess = isset($mess_Arr[0]) ? $mess_Arr[0] : '-- Операция остановлена. Но, ответ сервера не определен0 --';
         }else{ // Например, если страница была просто обновлена
             $mess = isset($mess_Arr[1]) ? $mess_Arr[1] : '-- Операция остановлена. Но, ответ сервера не определен1 --';
         }
// 1. При использовании SSE (событий сервера). Т.е. если был (будет) отправлен заголовок text/event-stream
        if(was_header('Content-Type', 'text/event-stream') > 0){
            sendMsg(time(), $mess, false);
        }else{
            echo $mess;
        }

         $flag = false;
     }
// 19.2. Основной способ прерывания итераций (индексирования или т.п.)
     if(connection_aborted()){
         $mess = isset($mess_Arr[2]) ? $mess_Arr[2] : '-- Операция остановлена. Но, ответ сервера не определен2 --';

         if(was_header('Content-Type', 'text/event-stream') > 0){
             sendMsg(time(), $mess, false);
         }else{
             echo $mess;
         }

         $flag = false;
     }

return $flag;
}

// 20. Функция РЕГИСТРОНЕЗАВИСИМО проверяет, был ли передан КЛИЕНТУ заголовок (или готовый к отправке) вида $head_before: $header_after
function was_header($header_before, $header_after){
// Например:  Content-Type: text/event-stream
/* Возвращает 0, если такого заголовка нет,
              1, если такой заголовок есть,
             -1 в случае ошибки.
*/
    if(!$header_before || !$header_after){
return -1;
    }

    $header_reg = '~'. preg_quote($header_before). '\s*\:\s*'. preg_quote($header_after). '~i'; // Если вдруг были отправлены заголовки не строго по стандарту
    $headers_Arr = headers_list();

    $flag_exists = false;
    for($i=0; $i < sizeof($headers_Arr); $i++){

        $flag_exists = preg_match($header_reg, $headers_Arr[$i]);
        if($flag_exists === false){
return -1;

        }elseif($flag_exists){
return $flag_exists;
        }
    }

return $flag_exists;
}

// Функция задает регулярное выражение для поиска индексов в индексном файле для того или иного метафана
function reg_find_endexes_metaph($as_metaph){
/* Регулярное выражение ищет что-то вроде:  =/a/b/s/n/1.txt         При этом что-то вроде:
                                            sk:/A|;                 $as_metaph = '/a/b/s/n/1.txt'
                                            sf:1|;
                                            sm:1|;
                                            +
*/
    return "~\=". preg_quote($as_metaph, '/'). "\n([^\+]*)\+~";
}
