<?php

namespace App\Http\Task\Token;

use App\Http\Security\TokenRotationService;
use App\Http\Task\QueuedTask;
use App\Http\Task\TaskHandler;
use App\Http\Task\TaskResult;

final class CvTokenRotationTaskHandler implements TaskHandler
{
    public function __construct(
        private readonly TokenRotationService $rotateHandler,
    ) {}

    public function canHandle(string $type): bool
    {
        return $type === 'cv_token_rotation';
    }

    public function handle(QueuedTask $task, string $appRoot): TaskResult
    {
        $profile = $task->get('profile');
        $count = max(1, (int) $task->get('count'));
        $tokens = $this->rotateHandler->rotate($profile, $count);
        return TaskResult::ok(implode("\n", $tokens));
    }
}
