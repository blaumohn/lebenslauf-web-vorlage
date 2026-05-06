<?php

namespace App\Http\Admin\Token;

use App\Http\Admin\AdminTask;
use App\Http\Admin\AdminTaskHandler;
use App\Http\Mail\MailMessage;
use App\Http\Mail\MailService;
use App\Http\Security\TokenRotationService;

final class CvTokenRotationTaskHandler implements AdminTaskHandler
{
    public function __construct(
        private readonly TokenRotationService $rotateHandler,
        private readonly MailService $mailService,
    ) {}

    public function canHandle(string $type): bool
    {
        return $type === 'cv_token_rotation';
    }

    public function handle(AdminTask $task, string $entryPath): void
    {
        $profile = $task->get('profile');
        $count = max(1, (int) $task->get('count'));

        $tokens = $this->rotateHandler->rotate($profile, $count);

        $this->mailService->send(new MailMessage(
            module: 'Admin',
            title: "CV-Token erneuert ({$profile})",
            body: implode("\n", $tokens),
        ));
    }
}
