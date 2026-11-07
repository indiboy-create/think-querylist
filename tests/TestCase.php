<?php

declare(strict_types=1);

namespace Youvision\ThinkQueryList\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected static string $baseUrl = '';

    protected static int $port = 0;

    /** @var resource|null */
    protected static $serverProcess = null;

    protected static string $serverLog = '';

    public static function setUpBeforeClass(): void
    {
        static::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        static::stopServer();
    }

    protected static function startServer(): void
    {
        static::$port = static::findFreePort();
        static::$baseUrl = 'http://127.0.0.1:' . static::$port;
        static::$serverLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_querylist_server_' . static::$port . '.log';

        $router = __DIR__ . DIRECTORY_SEPARATOR . 'Server' . DIRECTORY_SEPARATOR . 'router.php';
        $command = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            static::$port,
            escapeshellarg($router)
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', static::$serverLog, 'a'],
            2 => ['file', static::$serverLog, 'a'],
        ];

        $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__));
        if (!is_resource($process)) {
            throw new RuntimeException('无法启动测试 HTTP 服务器');
        }

        static::$serverProcess = $process;

        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', static::$port, $errno, $errstr, 0.05);
            if (is_resource($socket)) {
                fclose($socket);

                return;
            }
            usleep(50000);
        }

        static::stopServer();

        throw new RuntimeException('测试 HTTP 服务器启动超时：' . static::$serverLog);
    }

    protected static function stopServer(): void
    {
        if (!is_resource(static::$serverProcess)) {
            return;
        }

        $status = proc_get_status(static::$serverProcess);

        if (PHP_OS_FAMILY === 'Windows' && !empty($status['pid'])) {
            @exec('taskkill /F /T /PID ' . (int) $status['pid'] . ' 2>NUL');
        }

        @proc_terminate(static::$serverProcess);
        @proc_close(static::$serverProcess);

        static::$serverProcess = null;

        if (static::$serverLog !== '' && is_file(static::$serverLog)) {
            @unlink(static::$serverLog);
        }
    }

    protected static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException('无法获取测试端口：' . $errstr);
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($name === false) {
            return 18080;
        }

        return (int) substr(strrchr($name, ':'), 1);
    }

    protected function url(string $path = ''): string
    {
        return static::$baseUrl . $path;
    }
}