<?php

declare(strict_types=1);

namespace App\Tests;

use App\Cli\Config\AppConfigValidator;
use PHPUnit\Framework\TestCase;

final class AppConfigValidatorTest extends TestCase
{
    public function testAcceptsValidContactRecipient(): void
    {
        $errors = $this->validator()->validate([
            'CONTACT_TO_EMAIL' => 'kontakt@example.invalid',
        ]);

        self::assertSame([], $errors);
    }

    public function testRejectsEmptyContactRecipient(): void
    {
        $errors = $this->validator()->validate([
            'CONTACT_TO_EMAIL' => '',
        ]);

        self::assertSame(['CONTACT_TO_EMAIL darf nicht leer sein.'], $errors);
    }

    public function testRejectsInvalidContactRecipient(): void
    {
        $errors = $this->validator()->validate([
            'CONTACT_TO_EMAIL' => 'ungueltig',
        ]);

        self::assertSame(
            ['CONTACT_TO_EMAIL muss eine gueltige E-Mail-Adresse sein.'],
            $errors
        );
    }

    public function testIgnoresAbsentContactRecipient(): void
    {
        self::assertSame([], $this->validator()->validate([]));
    }

    private function validator(): AppConfigValidator
    {
        return new AppConfigValidator();
    }
}
