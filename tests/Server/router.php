<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function html_response(string $html, string $charset = 'utf-8'): void
{
    header('Content-Type: text/html; charset=' . $charset);
    echo $html;
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

if ($path === '/list') {
    html_response(<<<'HTML'
<html><body>
<div class="list">
  <div class="item"><h3><a href="/a">标题一</a></h3><span class="date">2026-01-01</span></div>
  <div class="item"><h3><a href="/b">标题二</a></h3><span class="date">2026-01-02</span></div>
</div>
</body></html>
HTML);
} elseif ($path === '/gbk') {
    html_response(iconv('UTF-8', 'GBK', '<html><body><h1 class="title">中文标题</h1></body></html>'), 'gbk');
} elseif ($path === '/headers') {
    json_response([
        'method' => $method,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        'query' => $_GET,
    ]);
} elseif ($path === '/echo') {
    json_response([
        'method' => $method,
        'post' => $_POST,
        'query' => $_GET,
    ]);
} elseif ($path === '/count') {
    $key = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_GET['key'] ?? 'default')) ?: 'default';
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_querylist_count_' . $key . '.txt';
    $count = is_file($file) ? (int) file_get_contents($file) : 0;
    $count++;
    file_put_contents($file, (string) $count);

    html_response('<html><body><div class="count">' . $count . '</div></body></html>');
} elseif ($path === '/cookies') {
    json_response(['cookies' => $_COOKIE]);
} elseif ($path === '/sleep') {
    usleep(((int) ($_GET['ms'] ?? 100)) * 1000);
    html_response('<html><body><div class="slept">done</div></body></html>');
} elseif ($path === '/status') {
    $code = (int) ($_GET['code'] ?? 500);
    json_response(['status' => $code], $code);
} else {
    json_response(['error' => 'not found', 'path' => $path], 404);
}