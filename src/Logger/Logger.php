<?php

namespace App\Logger;

class Logger
{
    private string $path;

    public function __construct(string $path)
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $this->path = $path;
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context = []): void
    {
        $timestamp = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $contextString = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $entry = sprintf("[%s] %s: %s %s\n", $timestamp, $level, $message, $contextString);
        file_put_contents($this->path, $entry, FILE_APPEND | LOCK_EX);
    }
}
