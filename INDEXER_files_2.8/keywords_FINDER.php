<?php

mb_internal_encoding("utf-8");
$internal_enc = mb_internal_encoding();
mb_regex_encoding($internal_enc);


// 0. Задаем значения опасных параметров по умолчанию (для начала)
$max_keywords_LEN = 0; $reg_keywords = ''; $message_to_user = ''; $ru_aff_FILE_NAME = '3'; $predlogi_final_PATH = ''; $ru_alfabet = ''; $enc_Arr = array(); $path_common_index_name = '';

// 1. Задаваемые параметры/функции
require __DIR__ . '/parametrs.php'; // Здесь параметрам даются целевые значения
require __DIR__ . '/common_functions.php';
require __DIR__ . '/array_logical.php';


$t0 = microtime(true);

header('Content-type: text/html; charset=utf-8');

// Кнопка запроса очередной порции ссылок
$INPUT_next_links = '<input src="/LOCAL_only/REDACTOR/img/arrow_right.png" style="background-image: none; margin: 10px 0 0 25px; display: block; vertical-align: top; width: 30px;" onclick="show_links_in_POPUP(\'links\')" class="buttons_REDACTOR next_links" title="Показать еще '. $links_NUM_max_output .' ссылок" type="image" />';

/* 2. **********************    POST  (Начало)  ***************************/
// 2.1 find_keywords
if(isset($_POST['find_keywords'])){ // Поиск по искомым словам (быть может, с учетом логического выражения)
    // 2.1.1. Проверяем входные данные
    if(mb_strlen($_POST['find_keywords'], $internal_enc) > $max_keywords_LEN){
        die('<p class="error_mes">Слишком длинный список (логическое выражение) искомых слов ('. mb_strlen($_POST['find_keywords'], $internal_enc).' символов). А допускается не более '. $max_keywords_LEN. ' символов.</p>');
    }

    if($_POST['find_keywords'] == ''){
        die('<p class="error_mes">искомые слова не заданы. Для поиска по искомым словам следует задать их. Можно использовать логическое выражение на основе  искомых слов.</p>');
    }

    $keywords = trim($_POST['find_keywords']); // искомые слова (логическое выражение с кл. словами)
    $keywords = mb_strtolower($keywords, $internal_enc);

// 2.1.2. Проверяем, нет ли среди искомых слов предлогов, местоимений и пр. Если есть - удаляем их и сообщаем клиенту
    $keywords_Arr = preg_split('|\s+|', $keywords);
// 2.1.3. Берем практически все известные предлоги, частицы, союзы, междометия, местоимения русского языка из ОНОНЧАТЕЛЬНОГО файла
    $predlogi_Arr = file($predlogi_final_PATH);

    $i_ili_Arr = array('и', 'или');

    $keywords_predlogi_Arr = array_filter($predlogi_Arr, function ($el) use ($keywords_Arr, $i_ili_Arr){ // Эти слова есть в файле предлогов, местоимений и пр.
        $el = trim($el);
        return in_array($el, $keywords_Arr) && !in_array($el, $i_ili_Arr);
    });

    $keywords_predlogi_Arr = array_map('trim', $keywords_predlogi_Arr);

    if(sizeof($keywords_predlogi_Arr) > 0){
        $output = '<p>Многие предлоги, местоимения, союзы, частицы русского языка исключаются из состава искомых слов и не участвуют в поиске. Эти слова НЕ были включены в состав поисковых искомых слов: <span style="font-weight: bold; background-color: #FBE2B4">'. implode(', ', $keywords_predlogi_Arr) .'</span>.</p>';
        echo $output;
    }

    $keywords_Arr = array_diff($keywords_Arr, $keywords_predlogi_Arr); // Массив кл. слов без предлогов, местоимений и т.д.
    $keywords_Arr = array_map('trim', $keywords_Arr);

    $keywords = implode(' ', $keywords_Arr);

// 2.1.4. Запускаем поиск по искомым (ключевым) словам
    find_keywords($keywords, $internal_enc, $reg_keywords, $message_to_user, $min_WORD_len, $metaphone_len, $path_DIR_name, $file_finded_FILES_name, $links_NUM_max_output, $INPUT_next_links, $ru_aff_FILE_NAME, $t0, $enc_Arr, $path_common_index_name);

$mess = check_ERRORS('Error|Произошла ошибка поиска файлов сайта по искомым выбранным словам.');
die($mess);
}
// 2.2. ask_keyword_links
if(isset($_POST['ask_keyword_links'])){ // Поиск по искомым словам (быть может, с учетом логического выражения)

    // 2.2.1. Проверяем входные данные
    if(!is_numeric($_POST['ask_keyword_links'])){
        die('<p class="error_mes">Запрос на показ дополнительных ссылок на файлы сайта, содержащие заданные искомые слова, содержит недопустимые символы.'. '</p>');
    }

    $link_last_num = $_POST['ask_keyword_links']; // Будем брать из файла ссылки, начиная с этого номера

    $file_names_Arr = ask_keyword_links($file_finded_FILES_name, $link_last_num, $links_NUM_max_output);

    $file_names_Arr = array_map(function ($el){
        $href = str_replace('\\', '/', $el);
        return '<li>'. '<a class="files" href="'. $href. '" title="Открыть в новой вкладке" target="_blank">'. $href. '</a></li>';
    }, $file_names_Arr); // Превращаем массив имен файлов в массив тегов <li>...</li> с соответствующими ссылками внутри них

    $str =  implode('', $file_names_Arr);

    if($str){
        echo $str;
    }else{
        echo '<p class="error_mes">Больше нет файлов, соответствующих указанным искомым словам.</p>';
    }
$mess = check_ERRORS('Error|Произошла ошибка поиска файлов сайта по искомым выбранным словам.');
die($mess);
}
// 2.3. link_description
if(isset($_POST['link_description'])){
// Получение описания (части содержимого) файла по искомым словам (быть может, с учетом логического выражения) - для подсказок

    $file_name_REL = preg_replace('|[^'. $ru_alfabet .'a-z0-9!~\?_/\\\.-]|i', '', $_POST['link_description']); // В utf-8

// 2.3.1. Определяем кодировку файла с перечнем файлов сайта и получаем массив, состоящий из имен этих файлов
    $rez_Arr = get_files_Arr($enc_Arr);
    $ALL_files_Arr = $rez_Arr[0];
    $ENC_FILE_names_all_files = $rez_Arr[1];

    if($ALL_files_Arr === -1){ // В случае ошибки
        die($ENC_FILE_names_all_files);
    }

    // Актуально, когда в имени файла содержится, например, кириллица
    $file_name_REL_encoded = ($ENC_FILE_names_all_files === $internal_enc) ? $file_name_REL : mb_convert_encoding($file_name_REL, $ENC_FILE_names_all_files, $internal_enc);


    $file_name_ABS = realpath($_SERVER['DOCUMENT_ROOT']. $file_name_REL_encoded);

// 2.3.2. Пытаемся читать содержимое тега <description />. Если такого тега нет, то берем начальную часть файла, за исключением тегов
    if($file_name_ABS){ // Если такой файл существует
        $file_str = file_get_contents($file_name_ABS, null, null, 0, 10000);

        $true_encoding = check_enc($file_str, $enc_Arr);
        if(!$true_encoding){
            die('Не удалось определить кодировку этого файла');
        }elseif(strtolower($true_encoding) !== strtolower($internal_enc)){
            $file_str = mb_convert_encoding($file_str, $internal_enc, $true_encoding);
        }

        preg_match('~<meta\s+name\s*=\s*(?:\'|")(?:description|DESCRIPTION)(?:\'|")([^>]*)/?>~', $file_str, $matches); // символы ? > могут восприниматься, как конец тега РНР. Доделать +++
// Доделать, с учетом возможного разного порядка description и content todo  +++
        $description = sizeof($matches) > 1 ? $matches[1] : null;

        if($description){
            $content = preg_match('~content\s*=\s*(?:\'|")([^\'"]*)(?:\'|")~', $description, $matches);
            $description = sizeof($matches) > 1 ? $matches[1] : null;
        }

        if($description){
            echo $description;
        }else{ // Если нет метатега <description ... /> или он - пустой (без контента), тогда
            $file_str = preg_replace('~<\?[\s\S]*?\?>|<\?[\s\S]*~', ' ', $file_str); // Удаляем все теги PHP.
            $file_str = preg_replace('~<head[^>]*>(?:[\s\S]*?)</head>~', '', $file_str); // берем начальную часть файла, за исключением раздела <head>
            $file_str = preg_replace('~<[^>]*?>~', ' ', $file_str); // Вырезаем все теги html, DOCTYPE и комментарии
            $file_str = preg_replace('~\s+~', ' ', $file_str);
            echo substr($file_str, 0, 3000);
        }

    }else{
        echo 'Ошибка! Похоже, файл <span style="font-weight: bold">'. $file_name_REL . '</span> отсутствует. Поэтому невозможно получить его описание.';
    }
$mess = check_ERRORS('Error|Произошла ошибка поиска файлов сайта по искомым выбранным словам.');
die($mess);
}


/***************************    /POST  (Конец)  ***************************/


/***************      ФУНКЦИИ      **************************/


function ask_keyword_links($file_finded_FILES_name, $link_last_num, $links_NUM_max_output){

    $file_names_Arr = array();

    $input = fopen($file_finded_FILES_name, 'r');
    if($input === false){
        die('<p class="error_mes">Невозможно открыть файл с перечнем имен файлов сайта и их индексами ('. $file_finded_FILES_name. ')</p>');
    }

$i = 0;
    while (($buffer = fgets($input)) !== false){
        if($i++ < $link_last_num){
            continue;
        }

        if($i <= $link_last_num + $links_NUM_max_output){
            $file_names_Arr[] = trim($buffer);
        }else{
            break;
        }

    }
    fclose($input);
return $file_names_Arr;
}


// Функция делает поиск по искомым словам, в т.ч., с учетом искомого логического выражения (для этого файлы сайта ДОЛЖНЫ БЫТЬ заранее проиндексированы)
function find_keywords($keywords, $internal_enc, $reg_keywords, $message_to_user, $min_WORD_len, $metaphone_len, $path_DIR_name, $file_finded_FILES_name, $links_NUM_max_output, $INPUT_next_links, $ru_aff_FILE_NAME, $t0, $enc_Arr, $path_common_index_name){


// 0. Проверяем искомые слова (кл. выражение)
if(preg_match($reg_keywords, $keywords, $matches)){ // Если обнаружится недопустимый символ
    die('<p class="error_mes">В искомых словах присутствует недопустимый символ: '. implode(' ', $matches).'</p>');
}

if(substr_count($keywords, '(', 0) !== substr_count($keywords, ')', 0)){
    die('<p class="error_mes">К искомых словах число открывающих скобок "(" равно '. substr_count($keywords, '(', 0). '; это не совпадает с числом закрывающих скобок ")", равным '.substr_count($keywords, ')', 0).'.</p>');
}


// 1. Нормализуем искомые слова (логическое выражение)
$keywords = preg_replace('/\(\s+/', '(', $keywords); // ( слово  ->  (слово
$keywords = preg_replace('/\s+\)/', ')', $keywords); // слово )  ->   слово)

$keywords = preg_replace('/\s+и\s+/', '&&', $keywords); // и -> &&
$keywords = preg_replace('/\(и\s+/', '(&&', $keywords);
$keywords = preg_replace('/\s+и\)/', '&&)', $keywords);

$keywords = preg_replace('/\s+или\s+/', '||', $keywords); // или -> ||
$keywords = preg_replace('/\(или\s+/', '(||', $keywords);
$keywords = preg_replace('/\s+или\)/', '||)', $keywords);


    $keywords1 = preg_replace('/\s+/', '', $keywords); // Временно удаляем все пробелы
    preg_match_all('/([&\|]{3,})/', $keywords1, $matches); // Нельзя &||  ||&&  и т.д.
    if(sizeof($matches[0]) > 0){ // Нельзя &&&, &&&&, ||| и т.д.
        die('<p class="error_mes">искомые слова содержат недопустимые последовательности символов: <b>'. implode(', ', $matches[0]). '</b>. Вместо "&&" в искомых словах может фигурировать "и", а вместо "||" - "или".</p>');
    }
    preg_match_all('/([^&]&[^&])|([^\|]\|[^\|])/', $keywords1, $matches);
    if(sizeof($matches[0]) > 0){ // Нельзя & или | одиночно. Допускаются только двойные: && или ||
        die('<p class="error_mes">искомые слова содержат одиночные символы: <b>&</b> или <b>|</b>. Это недопустимо.</p>');
    }

$keywords = preg_replace('/\s*&+\s*&+\s*/', '&&', $keywords);
$keywords = preg_replace('/\s*\|+\s*\|+\s*/', '||', $keywords);
$keywords = preg_replace('/\s+/', '&&', $keywords); // Все пробелы заменяем на &&

preg_match_all('/(\([&\|]+)|([&\|]+\))|(&\|)|(\|&)|(&{3,})|(\|{3,})/', $keywords, $matches, PREG_PATTERN_ORDER);
if(sizeof($matches[0]) > 0){ // Нельзя (&&   ||)   &&)   ||)

    die('<p class="error_mes">искомые слова содержат недопустимые последовательности символов: <b>'. implode(', ', $matches[0]). '</b>. Вместо "&&" в искомых словах может фигурировать "и", а вместо "||" - "или".</p>');
}
// Итак, получено логическое выражение вида слово1&&(слово2||слово3)

$special_symb_Arr = array('&&', '||', '(', ')', 1);

// 2. Добавляем пробелы перед и после &&  ||  (  )
for($i=0; $i < sizeof($special_symb_Arr); $i++){
    $keywords = str_replace($special_symb_Arr[$i], ' '. $special_symb_Arr[$i]. ' ', $keywords);
}
$keywords = trim(preg_replace('/\s+/', ' ', $keywords)); // Оставляем только по одному пробелу

$keywords_Arr = explode(' ', $keywords);

if(!is_dir($path_DIR_name)){
    die('<p>Похоже, индексация СОДЕРЖИМОГО файлов сайта еще не была проведена. Т.к. отсутствует каталог '. str_replace('\\', '/', $path_DIR_name). '</p><p>Для реализации поиска следует сделать индексирование СОДЕРЖИМОГО файлов сайта.</p>');
}

echo '1: '. (microtime(true) - $t0). '<br>';


// 3. *************   НЕЧЕТКИЙ ПОИСК   *****************
$fuzzy = $_POST['fuzzy'];

if($fuzzy === '1'){ // Если включен НЕчеткий поиск

    define('flag_perfom_working', 1);
// К каждому искомому слову добавляем слова с другими окончаниями (при наличии), согласно словаря русск. языка. Потом будет искаться КАЖДОЕ из этих слов
    require __DIR__ . '/FUZZY/keywords_FINDER_fuzzy.php';
}


// 3.1. Для каждого искомого слова из массива искомых (кл.) слов запускаем процедуру поиска близких слов (в том числе, с разными окончаниями, если поиск НЕчеткий)
    $words_to_find_Arr = array();
    $words_met_to_find_Arr = array();

$indexes_num_Arr = array(); // Массив вхождений каждого индекса: Array(0 => (Array( Индекс => ЧислоВхождений)), ...).
// Например, для логич. выражения "японский или япония" будет что-то типа:
/*  Array
(
    [0] => Array
        (
            [33] => 5
            [103] => 2
            [915] => 5
            [1011] => 2
        )
    [1] => ||
    [2] => Array
        (
            [33] => 1
            [99] => 3
            [103] => 1
            [358] => 1
            [988] => 1
        )
)
*/

    for($j=0; $j < sizeof($keywords_Arr); $j++){ // По массиву, содерж. элементы логического выражения (например: слово1&&(слово2||слово3) )

        $words_to_find_Arr[$j] = $keywords_Arr[$j]; // Массив логического выражения (только для показа клиенту, если требуется)
        $words_met_to_find_Arr[$j] = $keywords_Arr[$j];

        if(in_array($keywords_Arr[$j], $special_symb_Arr)){ // Пропускаем символы &&  ||  и т.д.
            $indexes_num_Arr[$j] = $keywords_Arr[$j];
            continue;
        }
        $word = $keywords_Arr[$j];

        if($fuzzy === '1'){ // Для НЕчеткого поиска
// 3.2. Массив всевозможных окончаний русск. яз.
            $ru_aff_Arr = array_map(function ($elem){
                return preg_split('|\s+|', $elem, -1, PREG_SPLIT_NO_EMPTY);
            }, file($ru_aff_FILE_NAME));

            // Вызываем для каждого искомого слова, полученного от клиента. Получится массив форм слова (с разными возможными окончаниями)
            $keyword_forms_Arr = check_WORD_in_DIC($ru_aff_Arr, $internal_enc, $word, $metaphone_len, $path_DIR_name, $path_common_index_name);

        }else{ // Для обычного поиска
            $keyword_forms_Arr =  array($word); // Будет массив лишь из одного слова (т.к. его формы НЕ ищем)
        }

$words_to_find_Arr[$j] = ' ('. implode(' || ', $keyword_forms_Arr). ') '; // Может выводиться клиенту (для информации о поисковом логическом выражении)


/*****   ПОЛУЧАЕМ МНОЖЕСТВА ИНДЕКСОВ ДЛЯ КАЖДОГО КЛЮЧЕВОГО СЛОВА   *****/
        $keyword_forms_Arr = array_map('translit1', $keyword_forms_Arr); // В транслит, чтобы можно было применить функцию metaphone()
        $keyword_forms_Arr = array_values(array_unique($keyword_forms_Arr));

// 3.3. Превращаем искомые слова в метафоны
// Иначе - заменяем слова на 1 (чтобы они не приводили логическое выражение в false). Символы && || (  )  1  оставляем, как есть
        $keyword_forms_Arr = array_map(function ($el) use ($min_WORD_len, $special_symb_Arr, $internal_enc, $metaphone_len) {
            if(in_array($el, $special_symb_Arr, true)){ // Для && || (  )  1
                return $el;
            }

            return (mb_strlen($el) >= $min_WORD_len ? do_metaphone1($el, $metaphone_len) : 1); // Заменяем короткие слова в логич. выражении на 1 (true)
        }, $keyword_forms_Arr);


$words_met_to_find_Arr[$j] = ' ('. implode(' || ', $keyword_forms_Arr). ') '; // Может выводиться клиенту (для информации о поисковом логическом выражении)


// 3.4. Создаем массив, соответствующий искомому логическому выражению, но где ВСЕ оставш. кл. слова (временно) заменены на 0 (false)
        $keywords_FALSE_Arr = array_map(function ($el) use ($min_WORD_len, $special_symb_Arr, $internal_enc, $metaphone_len) {
            if(in_array($el, $special_symb_Arr, true)){ // Для && || (  )  1
                return $el;
            }
            return ($el === 1) ? 1 : 0; // Заменяем все оставшиеся слова на 0 (false). Кроме тех, к-рые установлены равными 1
        }, $keyword_forms_Arr);


        if(sizeof($keyword_forms_Arr) === 1 && $keyword_forms_Arr[0] === 1){
            $indexes_num_Arr[$j] = array(1); // Например, для коротких искомых кл. слов
            continue;
        }else{
            $rez_Arr = get_indexes_Arr($keyword_forms_Arr, $special_symb_Arr, $path_DIR_name, $keywords_FALSE_Arr, $message_to_user, $path_common_index_name);
            $indexes_Arr_num_Arr_j = $rez_Arr[0]; // Соответствующий индексам массив их вхождений для каждой формы слова (в индексных файлах)
        }


// 4. Теперь следует объединить ранги при помощи функции array_mer__ge()) для одинаковых индексов и создать единый массив для них (это для каждого слова, с учетом его разных словоформ в случае НЕчеткого поиска)
        $tmp_Arr = array();
        foreach ($indexes_Arr_num_Arr_j as $item){
            $tmp_Arr = array_mer__ge($tmp_Arr, $item); // Объединяем ранги для каждой из форм слова $word (точнее, метафонов этих форм)
        }

//        $indexes_num_Arr[do_metaphone1(translit1($word), $metaphone_len)] = $tmp_Arr;
        $indexes_num_Arr[$j] = $tmp_Arr;
        unset($tmp_Arr);
/*  Массив вида:
  Array                         Индекс => Ранг-Файла-С-Этим-Индексом    (ранг потребуется для ранжирования при выводе списка найденных файлов на экран)
(                                                                       Ранг определяется для каждого файла, в котором есть, как минимум, одно вхождение словоформы
    [0] => Array               // Под индексом [0] подразумевается, например, искомое слово "права"
        (
            [1338] => 12       // В файле, имеющем индекс 1338, имеется 12 вхождений таких словоформ: право, права, прав, правами и т.д.
            [243] => 6
            [1324] => 4
            [1348] => 1
            [661] => 1
        )
    [1] => &&
    [2] => Array               // Это - следующее искомое слово
        (
            [988] => 18
            [243] => 9
            [564] => 9
            [1348] => 8
        )
    [3] => ||
    ......
)
*/
    } // Конец перебора кл. слов из логического выражения


    if($_POST['show_logical'] === '1'){ // Если требуется вывести и показать клиенту поисковое логическое выражение из искомых слов
        $logical_expression = '<p style="background-color: wheat; display: table-cell; font-size: 90%;">'. implode(' ', $words_to_find_Arr). '</p>';
        echo $logical_expression. '<p> </p>'; // Выводим  логическое выражение из слов (с учетом разных окончаний, в случае НЕчеткого поиска)

        $logical_expression_met = '<p style="background-color: wheat; display: table-cell; font-size: 90%;">'. implode(' ', $words_met_to_find_Arr). '</p>';
        echo $logical_expression_met; // Выводим  логическое выражение из метафонов (с учетом разных окончаний, в случае НЕчеткого поиска)
    }

    echo '2: '.  (microtime(true) - $t0). '<br>';
// 5. Создаем логич. выражение для последующей оценки при помощи eval()
    $a = $indexes_num_Arr;
    $bool_string_indexes = ''; // Будет что-то типа  $a[0] || $a[1] && $a[2] ...

    foreach ($a as $key => $item){
        if(is_array($item)){
            $bool_string_indexes .= '$a['. $key. ']'; // Массив индексов
        }else{
            $bool_string_indexes .= ' '. ($item). ' '; // Для  (  )  &&  ||
        }
    }


$operators_arrs_Arr = array('&&' => 'array_inters__ect', "||" => 'array_mer__ge');
// 6. Оцениваем получившееся логическое выражение из индексов. Находим индексы, соответствующие логич. выражению
    $rez_Arr = array_logical_converter($a, $bool_string_indexes, $operators_arrs_Arr);

    if($rez_Arr[0] === -1){ // Если ошибка в лог. выражении
        die($rez_Arr[1]);
    }
// Массив индексов найденных файлов сайта из файла files.txt, которые удовлетворяют искомому логическому выражению, с учетом ранга
    $TRUE_indexes_Arr = $rez_Arr[1];
/* Получится что-то вроде:
 Array                      // Индексы файлов, имеющие высокий ранг, будут идти первыми в массиве
(
    [33] => 6.9950738916256
    [1354] => 6.995
    [915] => 6.9947916666667
    [464] => 4.8095238095238
    [351] => 4
*/

echo '3: '.  (microtime(true) - $t0). '<br>';


// 7. Теперь на основе полученных индексов нужно получить имена файлов (из files.txt)
$TRUE_indexes_Arr_SIZE = sizeof($TRUE_indexes_Arr);

    if(!$TRUE_indexes_Arr_SIZE){ // Если ни один индекс не соответствует (логическому) искомому выражению
        die('<p class="error_mes">Данные искомые слова не обнаружены ни в одном из индексированных файлов.</p>');
    }


$file_names_Arr = array(); // Массив имен целевых (искомых) файлов, соответствующие найденным индексам

// 8. Читаем файл с перечнем  имен|индексов  всех файлов сайта
$ALL_files = file_get_contents(PATH_FILE_NAMES_ALL_FILES); // Для очень больших файлов - доделать обработку по частям todo +++
$ALL_files_txt_Arr = explode(PHP_EOL, $ALL_files); // Массив всех файлов сайта с индексами (строчки вида filename|index )
unset($ALL_files);

$ALL_files_Arr = array(); // Массив всех файлов сайта из files.txt. Вида:   Array ( Индекс => Имя )
    for($i=0; $i < sizeof($ALL_files_txt_Arr); $i++){
        $x_Arr = explode('|', $ALL_files_txt_Arr[$i]);

        if($x_Arr[0]){
            $ALL_files_Arr[trim($x_Arr[1])] = $x_Arr[0];
        }else{
            continue;
        }
    }


$TRUE_keys_Arr = array_keys($TRUE_indexes_Arr);
    foreach ($TRUE_keys_Arr as $index){

        $str = $ALL_files_Arr[$index];
        $file_names_Arr[$index] = substr($str, 0, strpos($str, ';'));
    }
//print_r($file_names_Arr);

// 9. Проверяем размерности массивов (на всякий случай)
if($TRUE_indexes_Arr_SIZE !== sizeof($file_names_Arr)){ // Значит, либо не для всех индексов были найдены файлы из списка в файле files.txt, либо какая-то иная ошибка
    die('<p class="error_mes">Почему-то размерности массива индексов ('. $TRUE_indexes_Arr_SIZE. ') и массива найденных файлов, соответствующих логическому выражению для искомых слов ('.  sizeof($file_names_Arr) .') НЕ совпадают. Произошла какая-то ошибка. См. Файл '. $_SERVER['PHP_SELF'] . ', стр.'. __LINE__ . '</p>');
}

echo '4: '. (microtime(true) - $t0). '<br>';


// 10. Сохраняем найденный перечень файлов в файл
file_put_contents($file_finded_FILES_name, implode(PHP_EOL, $file_names_Arr));

// 11. Оформляем полученные имена файлов для вывода на экран
$output = '<p>Затрачено времени: '. round(microtime(true) - $t0, 3). ' сек.</p>';
$output .= '<p class="">Всего найдено '. sizeof($file_names_Arr). ' файлов, соответствующим этим искомым словам. Из них показано <span id="num_showed_links">'. '</span>:';

if(sizeof($file_names_Arr) > $links_NUM_max_output){
    $output .= $INPUT_next_links;
}

echo '5-6: '. (microtime(true) - $t0). '<br>';

$output .= '</p>';
$output .= '<ol id="links">';

// 12. Определяем кодировку файла с перечнем файлов сайта и получаем массив, состоящий из имен этих файлов
$rez_Arr = get_files_Arr($enc_Arr);
$ALL_files_Arr = $rez_Arr[0];
$ENC_FILE_names_all_files = $rez_Arr[1];

    if($ALL_files_Arr === -1){ // В случае ошибки
        die($ENC_FILE_names_all_files);
    }

$file_names_Arr = array_values($file_names_Arr); // Индексы найденных файлов уже более не нужны, оставляем только их имена

$max_links_output_len = min($links_NUM_max_output, sizeof($file_names_Arr));
      for($i=0; $i < $max_links_output_len; $i++){

        // Имя файла перекодированное (только для вывода в виде информации на экран). Актуально, когда в имени файла содержится, например, кириллица
        $file_names_Arr[$i] = ($ENC_FILE_names_all_files === $internal_enc) ? $file_names_Arr[$i] : mb_convert_encoding($file_names_Arr[$i], $internal_enc, $ENC_FILE_names_all_files);

        $href = str_replace('\\', '/', $file_names_Arr[$i]);
        $output .= '<li>'. '<a class="files" href="'. $href. '" title="Открыть в новой вкладке" target="_blank">'. $href. '</a></li>';
    }
$output .= '</ol>';
echo $output;

}
