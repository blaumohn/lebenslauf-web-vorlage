<?php

namespace App\Cli\Config;

final class AppConfigValidator
{
    public function validate(array $values): array
    {
        $errors = [];
        if (array_key_exists('CONTACT_TO_EMAIL', $values)) {
            $errors = array_merge($errors, $this->validateContactEmail($values));
        }
        return $errors;
    }

    private function validateContactEmail(array $values): array
    {
        $value = trim((string) $values['CONTACT_TO_EMAIL']);
        if ($value === '') {
            return ['CONTACT_TO_EMAIL darf nicht leer sein.'];
        }
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return ['CONTACT_TO_EMAIL muss eine gueltige E-Mail-Adresse sein.'];
        }
        return [];
    }
}
