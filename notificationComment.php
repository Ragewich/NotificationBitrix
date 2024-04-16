<?php
$webhook = "http://192.168.0.16/rest/40/jhbvrexwqmfh1962";
$lastCommentsFile = "last_comments.txt";

while (true) {
    // Загружаем последние комментарии из файла, если он существует
    $sendNotifications = file_exists($lastCommentsFile);
    if ($sendNotifications) {
        $lastComments = unserialize(file_get_contents($lastCommentsFile));
    } else {
        $lastComments = [];
    }

    $startItem = 0;
    $perPageItem = 50;

    do {
        // Получаем список всех элементов
        $items = file_get_contents($webhook . "/crm.item.list?&entityTypeId=156&start=$startItem");
        $items = json_decode($items, true);

        foreach ($items["result"]["items"] as $item) {
            $itemId = $item["id"];

            // Получаем ID последнего комментария для этого элемента, если он есть
            $lastCommentId = isset($lastComments[$itemId]) ? $lastComments[$itemId] : 0;

            // Получаем список комментариев для этого элемента
            $start = 0;
            $perPage = 50;
            $lastNewComment = null;

            do {
                $result = file_get_contents($webhook . "/crm.timeline.comment.list?filter[ENTITY_ID]=$itemId&filter[ENTITY_TYPE]=DYNAMIC_156&select[]=ID&select[]=COMMENT&start=$start");
                $result = json_decode($result, true);

                foreach ($result["result"] as $comment) {
                    if ($comment["ID"] > $lastCommentId) {
                        // Получаем дополнительную информацию о комментарии
                        $commentDetails = file_get_contents($webhook . "/crm.timeline.comment.get?ID=" . $comment["ID"]);
                        $commentDetails = json_decode($commentDetails, true);

                        // Добавляем дополнительную информацию к комментарию
                        $comment = array_merge($comment, $commentDetails["result"]);

                        $lastNewComment = $comment;
                    }
                }

                $start += $perPage;
            } while (count($result["result"]) == $perPage);

            // Если есть новый комментарий и мы должны отправлять уведомления, отправляем уведомление
            if ($lastNewComment !== null && $sendNotifications) {
                // Получаем информацию об авторе комментария
                $authorInfo = file_get_contents($webhook . "/user.get?ID=" . $lastNewComment["AUTHOR_ID"]);
                $authorInfo = json_decode($authorInfo, true);

                // Если имя автора доступно, используем его в сообщении
                if (isset($authorInfo["result"][0]["NAME"])) {
                    $authorName = $authorInfo["result"][0]["NAME"];
                    if (isset($authorInfo["result"][0]["LAST_NAME"])) {
                        $authorName .= ' ' . $authorInfo["result"][0]["LAST_NAME"];
                    }
                } else {
                    $authorName = $lastNewComment["AUTHOR_ID"];
                }

                // Получаем информацию об элементе
                $itemInfo = file_get_contents($webhook . "/crm.item.get?entityTypeId=156&id=" . $itemId);
                $itemInfo = json_decode($itemInfo, true);

                // Получаем название задачи
                $itemTitle = $itemInfo["result"]["item"]["title"];

                // Получаем ID ответственного, исполнителя и наблюдателей
                $responsibleId = $itemInfo["result"]["item"]["assignedById"];
                $executorId = $itemInfo["result"]["item"]["ufCrm3_1667991424"];
                $observers = $itemInfo["result"]["item"]["observers"];

                // Собираем все ID в один массив и удаляем дубликаты
                $allIds = array_unique(array_merge([$responsibleId], [$executorId], $observers));

                // Удаляем ID автора комментария из массива
                if (($key = array_search($lastNewComment["AUTHOR_ID"], $allIds)) !== false) {
                    unset($allIds[$key]);
                }

                // Отправляем уведомление каждому уникальному ID
                $message = $authorName. " оставил комментарий к задаче : [URL=https://192.168.0.16/page/proektirovanie/pir_khmao_yuresk/type/156/details/$itemId/]" . 
                $itemTitle . "[/URL]. Комментарий: " . $lastNewComment["COMMENT"];
                foreach ($allIds as $id) {
                    file_get_contents($webhook . "/im.notify.system.add?USER_ID=$id&MESSAGE=" . urlencode($message));
                }
            }

            // Сохраняем ID последнего комментария
            $lastComments[$itemId] = $lastNewComment !== null ? $lastNewComment["ID"] : $lastCommentId;
        }

        $startItem += $perPageItem;
    } while (count($items["result"]["items"]) == $perPageItem);

    // Сохраняем последние комментарии в файл
    file_put_contents($lastCommentsFile, serialize($lastComments));

    // Ждем 30 секунд перед следующим запуском
    sleep(15);
}
?>