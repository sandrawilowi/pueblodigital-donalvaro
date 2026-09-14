<?php

declare(strict_types=1);

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

final class logsModel
{
    private string $basePath;
    private string $folder;
    private string $fileName;
    private int $maxFiles = 30;
    private Logger $logger;

    public function __construct(string $folder = 'app', string $fileName = 'app.log')
    {
        $this->basePath = rtrim(_URL_LOGS, '/\\');
        $this->folder = trim($folder, '/\\');
        $this->fileName = $fileName;

        $this->buildLogger();
    }

    private function buildLogger(): void
    {
        $directory = $this->basePath . DIRECTORY_SEPARATOR . $this->folder;

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . $this->fileName;

        $handler = new RotatingFileHandler(
            $filePath,
            $this->maxFiles,
            Logger::DEBUG
        );

        $formatter = new LineFormatter(
            "[%datetime%] [%level_name%] %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true
        );

        $handler->setFormatter($formatter);

        $this->logger = new Logger('app');
        $this->logger->pushHandler($handler);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function setMaxFiles(int $maxFiles): void
    {
        $this->maxFiles = $maxFiles;
        $this->buildLogger();
    }
}