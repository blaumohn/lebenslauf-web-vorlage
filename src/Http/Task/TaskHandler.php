<?php

namespace App\Http\Task;

interface TaskHandler
{
    public function canHandle(string $type): bool;

    public function handle(QueuedTask $task, string $appRoot): TaskResult;
}
