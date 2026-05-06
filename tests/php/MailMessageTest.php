<?php

declare(strict_types=1);

namespace App\Tests;

use App\Http\Mail\MailMessage;
use PHPUnit\Framework\TestCase;

final class MailMessageTest extends TestCase
{
    public function testSubjectFormatsAppNameAndModule(): void
    {
        $msg = new MailMessage('Contact', 'Neue Nachricht', '');
        $this->assertSame('[MeineApp/Contact] Neue Nachricht', $msg->subject('MeineApp'));
    }

    public function testSubjectUsesCorrectModule(): void
    {
        $msg = new MailMessage('Admin', 'Token erneuert', '');
        $this->assertSame('[App/Admin] Token erneuert', $msg->subject('App'));
    }
}
