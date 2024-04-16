<?php
$webhook = "http://192.168.0.16/rest/70/c32iggvm947il0sl";
$lastCommentsFile = "last_comments.txt";

$lastComments = [];

$startItem = 0;
$perPageItem = 50;

do {
    // Получаем список всех элементов
    $items = file_get_contents($webhook . "/crm.item.list?&entityTypeId=156&start=$startItem");
    $items = json_decode($items, true);

    foreach ($items["result"]["items"] as $item) {
        $itemId = $item["id"];

        // Получаем список комментариев для этого элемента
        $start = 0;
        $perPage = 50;
        $lastNewComment = null;

        do {
            $result = file_get_contents($webhook . "/crm.timeline.comment.list?filter[ENTITY_ID]=$itemId&filter[ENTITY_TYPE]=DYNAMIC_156&select[]=ID&select[]=COMMENT&start=$start");
            $result = json_decode($result, true);

            foreach ($result["result"] as $comment) {
                if ($lastNewComment === null || $comment["ID"] > $lastNewComment["ID"]) {
                    $lastNewComment = $comment;
                }
            }

            $start += $perPage;
        } while (count($result["result"]) == $perPage);

        // Сохраняем ID последнего комментария
        $lastComments[$itemId] = $lastNewComment !== null ? $lastNewComment["ID"] : 0;
    }

    $startItem += $perPageItem;
} while (count($items["result"]["items"]) == $perPageItem);

// Сохраняем последние комментарии в файл
file_put_contents($lastCommentsFile, serialize($lastComments));
?>