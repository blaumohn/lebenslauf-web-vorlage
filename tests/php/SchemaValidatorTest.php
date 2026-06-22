<?php

declare(strict_types=1);

use App\Http\SchemaValidator;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorTest extends TestCase
{
    public function testValidDataPasses(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $errors = $validator->validate($this->validData());
        $this->assertSame([], $errors);
    }

    public function testInvalidNameFails(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        $data['kopfdaten']['name'] = 'Max Mustermann';

        $errors = $validator->validate($data);
        $this->assertNotEmpty($errors);
    }

    public function testEducationDescriptionIsOptional(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        unset($data['ausbildung'][0]['beschreibung']);

        $errors = $validator->validate($data);
        $this->assertSame([], $errors);
    }

    public function testEducationDegreeIsOptional(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        unset($data['ausbildung'][0]['grad']);

        $errors = $validator->validate($data);
        $this->assertSame([], $errors);
    }

    public function testEducationNeedsDegreeOrDescription(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        unset($data['ausbildung'][0]['grad']);
        unset($data['ausbildung'][0]['beschreibung']);

        $errors = $validator->validate($data);
        $this->assertNotEmpty($errors);
    }

    private function schemaPath(): string
    {
        return dirname(__DIR__, 2) . '/src/resources/build/schemas/lebenslauf.schema.json';
    }

    private function validData(): array
    {
        return [
            'kopfdaten' => [
                'name' => [
                    'voll' => 'Max Mustermann',
                    'kurz' => 'Max M.',
                ],
                'bereich' => 'Softwareentwicklung',
                'ort' => 'Berlin',
                'email' => 'max@example.com',
                'telefon' => '+49 123 456',
            ],
            'motivation' => 'Kurzbeschreibung.',
            'faehigkeiten' => [
                [
                    'stufe' => 'Senior',
                    'wert' => 4,
                    'technologien' => ['PHP', 'SQL'],
                ],
            ],
            'berufserfahrung' => [
                [
                    'titel' => 'Entwickler',
                    'zeitraum' => '2020-2023',
                    'punkte' => [
                        [
                            'tags' => ['PHP'],
                            'text' => 'Backend-Entwicklung.',
                        ],
                    ],
                    'ort' => 'Berlin',
                    'unternehmen' => 'Beispiel GmbH',
                ],
            ],
            'sprachen' => [
                [
                    'sprache' => 'Deutsch',
                    'stufe' => 'Muttersprache',
                ],
            ],
            'ausbildung' => [
                [
                    'uni' => 'Uni',
                    'grad' => 'BSc',
                    'beschreibung' => 'Informatik',
                ],
            ],
            'interessen' => ['Lesen', 'Wandern'],
        ];
    }
}
