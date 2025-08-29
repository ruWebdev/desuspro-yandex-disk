<?php
// Публичная ссылка на Яндекс.Диск (или возьмём её из GET параметра ?url=...)
$public_url = isset($_GET['url']) ? $_GET['url'] : "https://yadi.sk/i/hxV9mI6oFOYf0Q";

// Формируем URL для API
$api_url = "https://cloud-api.yandex.net/v1/disk/public/resources/download?public_key=" . urlencode($public_url);

// Получаем JSON-ответ
$response = file_get_contents($api_url);
if ($response === FALSE) {
    die("Ошибка: не удалось обратиться к API Яндекс.Диска");
}

// Декодируем JSON
$data = json_decode($response, true);
if (!isset($data["href"])) {
    die("Ошибка: не удалось получить прямую ссылку. Ответ API: " . $response);
}

// Прямая ссылка на файл
$file_url = $data["href"];

// --- Проверяем MIME-тип ---
$headers = get_headers($file_url, 1);
if ($headers === FALSE || !isset($headers["Content-Type"])) {
    die("Ошибка: не удалось определить MIME-тип файла");
}

$mime = is_array($headers["Content-Type"]) ? end($headers["Content-Type"]) : $headers["Content-Type"];

// Допустимые MIME-типы и расширения
$mime_map = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/gif"  => "gif",
    "image/webp" => "webp",
];

if (!isset($mime_map[$mime])) {
    die("Файл не является изображением (MIME: $mime)");
}

// --- Загружаем содержимое файла ---
$file_content = file_get_contents($file_url);
if ($file_content === FALSE) {
    die("Ошибка: не удалось скачать файл");
}

// --- Директория для сохранения ---
$save_dir = __DIR__ . "/downloads/";

// Создаём папку, если нет
if (!is_dir($save_dir)) {
    mkdir($save_dir, 0777, true);
}

// Генерируем имя файла с нужным расширением
$save_name = "yadisk_file_" . time() . "." . $mime_map[$mime];

// Полный путь для сохранения
$save_path = $save_dir . $save_name;

// Сохраняем файл
if (file_put_contents($save_path, $file_content) !== false) {
    echo "Файл успешно сохранён: " . $save_path . " (MIME: $mime)";
} else {
    echo "Ошибка: не удалось сохранить файл";
}
