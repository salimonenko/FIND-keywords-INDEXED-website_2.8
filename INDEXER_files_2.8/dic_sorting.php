<?php
// Программа сортирует файл-словарь ru.dic. И Добавляет в файлы 1.txt (из каталога metaphones) признаки присутствия каждого словарного слова в файле-словаре (ru.dic)

mb_internal_encoding("utf-8");
$internal_enc = mb_internal_encoding();
mb_regex_encoding($internal_enc);


if(!defined('flag_perfom_working') || (flag_perfom_working != '1')){
    header('Content-type: text/html; charset=utf-8');

    die('Эту программу нельзя запускать непосредственно. Access forbidden.');
}


// После окончания работы функции сортировки и индексации словаря удаляем флаг-файл, чтобы остановить отправку событий сервера
register_shutdown_function(function () use ($DO_working_flag_FILE){
    if(file_exists($DO_working_flag_FILE)){
        $flag_unl = @unlink($DO_working_flag_FILE);
        if($flag_unl){
            $mess = '<p class="info_mes">Сортировка и индексация файла-словаря остановлены.</p>';
        }else{
            $mess = '<p class=error_mes">Ошибка: НЕ получилось удалить флаг-файл <b>'. basename($DO_working_flag_FILE) .'</b>. Попробуйте сделать это вручную, нажав кнопку "Остановить индексирование".</p>';
        }
        die($mess);
    }
});

// Функция сортирует словарь
function DIC_sort($ru_dic_FILE_NAME_saved, $ru_dic_FILE_NAME, $path_DIR_name, $path_common_index_name, $min_WORD_len, $metaphone_len, $DO_working_flag_FILE, $ru_dic_indexing_info_file, $min_index_FILE_len){

 file_put_contents($DO_working_flag_FILE, ''); // Создаем файл-флаг. Если он присутствует, то итерации цикла перебора файлов (содержащихся в файле files.txt) будут продолжаться. Если нет - то цикл будет остановлен. Сессии пока не используем, т.к. они не наглядны. Да и не нужны, т.к. придется делать ненужные куки.


// 1. Сортировка словаря
    $dic_Arr = file($ru_dic_FILE_NAME_saved); // Массив всех слов из русского словаря


$dic_Arr = array_map(function ($el){
    $el_Arr = explode('/', $el);
    if(sizeof($el_Arr) > 1){
        return mb_strtolower($el_Arr[0]) . '/'. $el_Arr[1];
    }else{
        return mb_strtolower($el);
    }
}, $dic_Arr);


sort($dic_Arr, SORT_REGULAR);


file_put_contents($ru_dic_FILE_NAME, implode("", $dic_Arr));


$mess = check_ERRORS('Error|Произошла ошибка при сортировке файла '. $ru_dic_FILE_NAME_saved. '. ');
if($mess){
    die($mess);
//    sendMsg(time(), $mess, false);
}




// 2. Если ошибок при сортировке не было
$mess = '<p>1. Файл-словарь '. $ru_dic_FILE_NAME . ' успешно отсортирован...</p>';
echo $mess;
//    sendMsg(time(), $mess, false);

// 3. Добавляем в файлы 1.txt (из каталога metaphones) признаки присутствия в файле-словаре (ru.dic)

// 3.1. Создаем каталог для подкаталогов-символов - частей метафонов
    if(!is_dir($path_DIR_name)){
        mkdir($path_DIR_name);
    }

$t = microtime(true);


// 3.2. Слова, присутствующие в файле-словаре, метафонизируем и к последним двум символам их метафонов добавляем:
// 1. Признак присутствия "1", если у конкретного слова суффикса (вида /AS) НЕТ. Получится что-то типа: ag:1|;
// 2. Сам суффикс, если он ЕСТЬ.                                                 Получится что-то типа: ag:/AS|;
//
$mess_Arr = array('<p class="info_mes"><b>Индексирование файла-словаря остановлено.</b></p>', '');



for($i=0; $i < sizeof($dic_Arr); $i++){ // Первый элемент этого массива - цифра - общее число слов словаря. Ее не индексируем
// Проверяем, следует ли прерывать итерацию цикла индексирования словаря
    /* В т.ч. проверяем, присутствует ли флаговый файл. Если да, то делаем следующую итерацию. Если нет - прекращаем цикл */
    if(!must_continue($mess_Arr, $DO_working_flag_FILE, false, null, null, null)){
        die($mess_Arr[0]);
    }

    $dic_word = trim(translit1($dic_Arr[$i])); // Для метафонизации

    $dic_word_Arr = explode('/', $dic_word);
    $dic_word_WORD = $dic_word_Arr[0];
    $dic_word_SUFF = isset($dic_word_Arr[1]) ? '/'. $dic_word_Arr[1] : '1';

    $keyword_metaph = do_metaphone1($dic_word_WORD, $metaphone_len); // Превращаем в metaphone


set_time_limit(40); // С этого момента скрипт будет выполняться не более указанного количества секунд (каждая итерация цикла). Точнее, будет выбрано минимальное время из указанного количества секунд и установленного в настройках (файл php.ini)

    if(strlen($keyword_metaph) < $min_WORD_len){ // Слишком короткие слова из словаря не индексируем, не берем
        continue;
    }

    $first_letter = substr($keyword_metaph, 0, 1);
    $COMMON_file_name = $path_DIR_name. $path_common_index_name. $first_letter. '.txt';

    if(!is_file($COMMON_file_name)){
        file_put_contents($COMMON_file_name, '');
        $str_COMMON_FILE = ''; // Строка-содержимое файла common_*.txt в каталоге metaphones. Ее обнуляем каждый раз, для создаваемого файла common_*.txt
    }else{
        $str_COMMON_FILE = file_get_contents($COMMON_file_name);
    }




$str_COMMON_FILE = LAST_met_2_index($keyword_metaph, '', $dic_word_SUFF, true, $path_DIR_name, $COMMON_file_name, '', array(), '', $dic_Arr[$i], $str_COMMON_FILE, $path_common_index_name, $min_index_FILE_len); // Та же самая функция, к-рая используется для индексации (содержимого, т.е. слов) файлов сайта


    if(microtime(true) - $t > 1){ // Запасаем номер текущей проиндексированной строки из файла-словаря для последующего извлечения и отправки его клиенту (через событие сервера)
        $t = microtime(true);
        file_put_contents($ru_dic_indexing_info_file, 'Индексируется (из словаря '. basename(realpath($ru_dic_FILE_NAME)) .') слово номер: <span class="waiting">'.$i.'</span> из '.sizeof($dic_Arr));
        flush();
    }


}


$mess = check_ERRORS('Error|Произошла ошибка при индексации слов из файла-словаря.');
if($mess){
    die($mess);
//    sendMsg(time(), $mess, false);
}

$mess = '<p>2. Файл-словарь '. $ru_dic_FILE_NAME . ' успешно проиндексирован.</p>';
echo $mess;
//    sendMsg(time(), $mess, false);


die();

}
