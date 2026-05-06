<?php

namespace App\Http\Admin;

interface AdminTaskHandler
{
    public function canHandle(string $type): bool;

    public function handle(AdminTask $task, string $entryPath): void;
}
