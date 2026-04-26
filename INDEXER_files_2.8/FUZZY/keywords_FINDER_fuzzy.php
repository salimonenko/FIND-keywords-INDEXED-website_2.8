<?php
// Программа (функция) для искомого (ключевого) слова дает всевозможные его формы (путем вариации окончаний), исходя из правил русского языка. На основе файла ru.aff
/* 1. Вначале пытаемся определить суффикс (типа /BKLM) слова, если оно содержится в словаре. Это возможно, если слово задано в начальной форме.
      Анализируем каждое окончание из файла суффиксов ru.aff: содержится ли оно в конце слова?
   2. Если содержится, то составляем возможные НАЧАЛЬНЫЕ словоформы, с учетом этих окончаний.
   3. Пытаемся определить, содержится ли каждая из начальных словоформ в словаре. Если да, то определяем суффикс.
   4. Определяем среди начальных словоформ ту, которая получены путем отбрасывания (или замены) окончания МИНИМАЛЬНОГО размера. ГИПОТЕЗА: это и будет, скорее всего, искомая начальная словоформа.
   5. Склоняем эту словоформу, с учетом ее суффикса (взятого из словаря).
   6. Добавляем исходное искомое слово в массив просклоненных словоформ.
*/

error_reporting(E_ALL);

mb_internal_encoding("UTF-8");
$internal_enc = mb_internal_encoding();
mb_regex_encoding("utf-8");

if(!defined('flag_perfom_working') || (flag_perfom_working != '1')) {
    header('Content-type: text/html; charset=utf-8');
    die('Эту программу нельзя запускать непосредственно. Access forbidden.');
}



/**********     ФУНКЦИИ PHP     **************************/

function check_WORD_in_DIC($ru_aff_Arr, $internal_enc, $word, $metaphone_len, $path_DIR_name, $path_common_index_name){

$t0 = microtime(true);

set_time_limit(40); // С этого момента скрипт будет выполняться не более указанного количества секунд (каждая итерация цикла)


$word = mb_strtolower($word, $internal_enc);

//$finded_Arr = array(); // Массив строчек из файла ru.aff, содержащих окончания, соответствующие слову.
$finded_okonchan_len_Arr = array(); // Массив длин окончаний, убранных из слова (см. столбец 3)



// 1. Находим начальные формы слова. Анализируем КАЖДОЕ окончание из файла суффиксов ru.aff: содержится ли оно в конце слова?
for($i=0; $i < sizeof($ru_aff_Arr); $i++){

    if(isset($ru_aff_Arr[$i][3])){
        if(preg_match('|'. $ru_aff_Arr[$i][3]. '$|' , $word) != false){
            // Если окончание содержится в слове, то убираем это окончание и делаем соотв. замену на окончание из столбца [2]
            $adding = ($ru_aff_Arr[$i][2] !== '0') ? $ru_aff_Arr[$i][2] : '';

            if($ru_aff_Arr[$i][3] !== '0'){
                $word_replaced = preg_replace('|'. $ru_aff_Arr[$i][3] .'$|', $adding, $word);
            }else{
                $word_replaced = $word. $adding;
            }

// А теперь проверяем соответствие столбцу [4]
            if(preg_match('|'. $ru_aff_Arr[$i][4]. '$|' , $word_replaced) != false){
                $finded_okonchan_len_Arr[$word_replaced] = mb_strlen($ru_aff_Arr[$i][3], $internal_enc);

            }
        // Если окончание не содержится в искомом слове, тогда, может, последние символы слова соответствуют окончанию из следующего столбца?
        // Это если слово само является начальной формой.
        }elseif(isset($ru_aff_Arr[$i][4])){
            if(preg_match('|'. $ru_aff_Arr[$i][4]. '$|' , $word) != false){
                $finded_okonchan_len_Arr[$word] = 0;
            }
        }

    }
}

//$finded_Arr = array_values($finded_Arr);

asort($finded_okonchan_len_Arr);
/*  Что-то типа:
  Array
(
    [ногу] => 0
    [ного] => 1
    [нога] => 1
    [ног] => 1
    [ногуть] => 1
    [ножать] => 2
    [ночь] => 2
)
*/

//print_r($finded_okonchan_len_Arr);


// 2. Массив начальных форм слова (именит. падеж, неопределенная форма глагола и т.д.). Они м.б. ошибочными, их следует проверить
$finded_Arr1 = array_keys($finded_okonchan_len_Arr);
//print_r($finded_Arr1); // Массив возможных начальных форм слова (плюс - само искомое слово). Будет что-то типа:
/* Array
(
    [0] => делал
    [1] => делать
    [2] => деласть
    [3] => деласти
)
или:
   Array
(
    [0] => ногу
    [1] => ного
    [2] => нога
    [3] => ног
    [4] => ногуть
    [5] => ножать
    [6] => ночь
)
*/

//print_r($finded_Arr1);

$word_DIC_suff_Arr = array(); // Массив искомых слов (слово-суффиксов), к-рые в начальной форме. Типа  сумма/I
/* !! Там могут быть заведомо неправильные слова, например, "ножать". Это вызвано метафонизацией ("nsht")
      Там будет что-то вроде:
  Array
(
    [0] => нога/I
    [1] => ножать/BLMP
    [2] => ночь/N
)
*/

// 3. Проверяем каждое из найденных слов в начальной форме: содержится ли оно в словаре ru.dic ? Т.е. проверяем, в начальной ли оно форме (если содержится).
// Когда-то ранее проверка делалась через маркеры-позиции. Теперь - путем поиска, содержится ли признак присутствия ("1" или суффикс) слова в соответствующем отдельном инд. файле 1.txt или в общем инд. файле вида /common_b.txt
$flag_word_exists = false;
for($i=0; $i < sizeof($finded_Arr1); $i++){ // По каждой, сконструированной начальной(?) форме слова, начиная с той, при формировании которой убиралось окончание наименьшей длины

    $keyword_metaph = do_metaphone1(translit1($finded_Arr1[$i]), $metaphone_len);

    $LAST_met_path_Arr = create_path_index_file($keyword_metaph, $path_DIR_name, false);
//    $index_FILE = $LAST_met_path_Arr[0];
    $LAST_met_2 = $LAST_met_path_Arr[1];
    $index_FILE_rel = trim($LAST_met_path_Arr[2]);

        $index_FILE_str_Arr = get_metaph_indexes($keyword_metaph, null, $path_DIR_name, $path_common_index_name, $index_FILE_rel, array());
        $index_FILE_str = $index_FILE_str_Arr[0];

        $index_FILE_Arr = explode("\n", $index_FILE_str);

        for($z=0; $z < sizeof($index_FILE_Arr); $z++){ // По каждой строчке индексного файла
            $substr1 = $LAST_met_2. ':1|'; // Без суффикса
            $substr2 = $LAST_met_2. ':/'; // С суффиксом

            if(strstr($index_FILE_Arr[$z], $substr1) !== false || strstr($index_FILE_Arr[$z], $substr2) !== false){ // Значит, признак присутствия имеется в этой строчке. Т.е. данное слово ЕСТЬ в файле-словаре
                preg_match('~\:/([^\|]*)\|~', $index_FILE_Arr[$z], $matches);

                if(sizeof($matches) > 0){ // Если начало строки имеет примерный вид:  tg:/KL|
                    $suff = '/'. $matches[1];
                }else{
                    $suff = ''; // Если начало строки имеет примерный вид:  tg:1|
                }
                $word_DIC_suff_Arr[] = $finded_Arr1[$i]. $suff; // Слово-суффикс. Например: нога/I
                $flag_word_exists = true;
        break;
            }
        }

        if($flag_word_exists){ // Если одно из слов, находящихся в массиве первыми, есть в словаре, то остальные слова не проверяем
break;
        }
}


// 4. Теперь каждое слово в начальной форме можно БЫ просклонять по разным окончаниям, в зависимости от суффикса из файла ru.dic
// Но, фактически - только одно слово, т.к. оно получено на основе словаря путем удаления МИНИМАЛЬНОЙ длины окончания. Остальные - не склоняем.
// ГИПОТЕЗА: скорее всего, именно это слово является однокоренным с искомым словом.
$words_to_find_Arr = array(); // Выходной массив слов для последующего поиска среди метафонов (с учетом разных окончаний)
/*   Там будет что-то вроде:
        Array
        (
            [0] => сумма
            [1] => суммы
            [2] => сумму
            [3] => суммой
            [4] => суммою
            [5] => сумме
            [6] => сумм
            [7] => суммами
            [8] => суммам
            [9] => суммах
        )
*/
// 5. По каждому слово-суффиксу (например: сумма/I )
    for($i=0; $i < sizeof($word_DIC_suff_Arr); $i++){

        $pos = strpos($word_DIC_suff_Arr[$i], '/');
        if($pos !== false){
            $DIS_suf = trim(substr($word_DIC_suff_Arr[$i], strpos($word_DIC_suff_Arr[$i], '/') + 1)); // Суффикс БЕЗ слова из файла ru.dic
            $DIC_word = trim(substr($word_DIC_suff_Arr[$i], 0, strpos($word_DIC_suff_Arr[$i], '/'))); // Слово БЕЗ суффикса из файла ru.dic
        }else{
            $DIS_suf = '';
            $DIC_word = trim($word_DIC_suff_Arr[$i]);
        }


// 5.1. Вначале в этот массив добавляем само слово в начальной форме (именительный падеж существительного, неопределенная форма глагола и т.п.)
$words_to_find_Arr[] = $DIC_word;

        $suf_Arr = array_filter($ru_aff_Arr, function ($el) use ($DIS_suf) { // Для конкретного слова берем все строки из файла ru.aff, только с суффиксом $DIS_suf
            if(isset($el[1]) && sizeof($el) > 4){
// 5.2. Если в совокупности суффиксов из файла ru.dic (например, BLMP) есть хотя бы один суффикс из файла ru.aff (например, B)
                return strpos($DIS_suf, $el[1]) !== false;
            }else{
                return null;
            }
        });
        $suf_Arr = array_values($suf_Arr);

        for($j=0; $j < sizeof($suf_Arr); $j++){ // По каждой строчке из файла ru.aff, содержащей суффикс $DIS_suf

            if(preg_match('|'. $suf_Arr[$j][4]. '$|', $DIC_word)){
                if($suf_Arr[$j][2] === '0'){
                    $removed = '';
                }else{
                    $removed = $suf_Arr[$j][2];
                }

                if($suf_Arr[$j][3] === '0'){
                    $replacement = '';
                }else{
                    $replacement = $suf_Arr[$j][3];
                }

                $tmp = preg_replace('|'. $removed. '$|', $replacement, $DIC_word);
                $words_to_find_Arr[] = $tmp;
            }
        }
    }


// 6. Бывают искомые слова, которых нет в словаре. Например, слово "иван". Такие слова НЕ вошли в массив $word_DIC_suff_Arr. Поэтому, для надежности, нужно добавить искомое слово (то самое, к-рое пришло от клиента) СНОВА
$words_to_find_Arr[] = $word; // Если оно в массиве уже есть, то ниже оно останется только в единственном числе

$words_to_find_Arr = array_values($words_to_find_Arr);
$words_to_find_Arr = array_unique($words_to_find_Arr);

return $words_to_find_Arr;
}
