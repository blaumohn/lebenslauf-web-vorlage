<?php

namespace App\Http;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Validator;

final class SchemaValidator
{
    public function __construct(private string $schemaPath) {}

    public function validate(mixed $data): array
    {
        [$loader, $schema, $schemaError] = $this->loadSchema();
        if ($schemaError !== null) {
            return [$schemaError];
        }

        if (is_array($data)) {
            $data = $this->normalizeArrayData($data);
            if ($data === null) {
                return ['Daten sind ungültig.'];
            }
        }

        $validator = new Validator($loader);
        $result = $validator->schemaValidation($data, $schema);
        if ($result === null) {
            return [];
        }

        return $this->flattenErrors((new ErrorFormatter())->format($result));
    }

    private function loadSchema(): array
    {
        $schemaJson = file_get_contents($this->schemaPath);
        if ($schemaJson === false) {
            return [null, null, 'Schema konnte nicht geladen werden.'];
        }

        $decoded = json_decode($schemaJson);
        if ($decoded === null) {
            return [null, null, 'Schema ist ungültig.'];
        }

        $resolver = new SchemaResolver();
        $commonSchemaPath = dirname($this->schemaPath) . '/common.schema.json';
        if (is_file($commonSchemaPath)) {
            $resolver->registerFile('schema:///common.schema.json', $commonSchemaPath);
        }
        $loader = new SchemaLoader(null, $resolver);
        try {
            $schema = is_bool($decoded)
                ? $loader->loadBooleanSchema($decoded)
                : $loader->loadObjectSchema($decoded);
        } catch (\Throwable) {
            return [null, null, 'Schema ist ungültig.'];
        }

        return [$loader, $schema, null];
    }

    private function flattenErrors(array $errors, string $prefix = ''): array
    {
        $messages = [];
        foreach ($errors as $key => $value) {
            $path = $prefix === '' ? $key : $prefix . '.' . $key;
            if (!is_array($value)) {
                $messages[] = $path . ': ' . (string) $value;
                continue;
            }
            $messages = array_merge($messages, $this->flattenErrors($value, $path));
        }
        return $messages;
    }

    private function normalizeArrayData(array $data): mixed
    {
        $normalized = json_decode(json_encode($data));
        if ($normalized === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $normalized;
    }
}
