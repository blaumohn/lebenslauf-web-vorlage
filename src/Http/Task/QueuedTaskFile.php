<?php

namespace App\Http\Task;

final class QueuedTaskFile
{
    public static function load(string $filePath): QueuedTask
    {
        if (!is_file($filePath)) {
            throw new \RuntimeException("Task nicht gefunden: {$filePath}");
        }
        $parsed = parse_ini_file($filePath, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException("Task nicht lesbar: {$filePath}");
        }
        $section = $parsed['task'] ?? [];
        $type = (string) ($section['type'] ?? '');
        if ($type === '') {
            throw new \RuntimeException("Task fehlt type: {$filePath}");
        }
        return QueuedTask::fromParsed($type, $section);
    }
}
