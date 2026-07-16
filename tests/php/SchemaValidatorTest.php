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

    public function testInvalidTitleFails(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        $data['titel'] = [];

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

    public function testEducationEmptyDegreeFailsInsteadOfBeingTreatedAsAbsent(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        $data['ausbildung'][0]['grad'] = '';

        $errors = $validator->validate($data);
        $this->assertNotEmpty($errors);
    }

    public function testCompanyNeedsAtLeastOnePosition(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        $data['berufserfahrung'][0]['stellen'] = [];

        $errors = $validator->validate($data);
        $this->assertNotEmpty($errors);
    }

    public function testStationIsValidEmploymentExperience(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        $data['berufserfahrung'][] = [
            'station' => 'Elternzeit & Weiterbildung',
            'zeitraum' => '2024-2025',
            'beschreibung' => 'Betreuung und Weiterbildung.',
        ];

        $errors = $validator->validate($data);

        $this->assertSame([], $errors);
    }

    public function testStationRejectsTypeInputField(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        $data['berufserfahrung'][] = [
            'station' => 'Elternzeit & Weiterbildung',
            'typ' => 'karenz',
            'zeitraum' => '2024-2025',
            'beschreibung' => 'Betreuung und Weiterbildung.',
        ];

        $errors = $validator->validate($data);

        $this->assertNotEmpty($errors);
    }

    public function testPositionRejectsLegacyGroupField(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        $data['berufserfahrung'][0]['stellen'][0]['stelleGruppe'] = [
            'letzteStelle' => true,
        ];

        $errors = $validator->validate($data);
        $this->assertNotEmpty($errors);
    }

    public function testSkillGroupWithoutRatingIsValid(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        $data['kenntnisse'] = [
            [
                'tags' => [
                    [
                        'de' => 'Kommunikation',
                        'en' => 'Communication',
                    ],
                ],
            ],
        ];

        $errors = $validator->validate($data);

        $this->assertSame([], $errors);
    }

    public function testKnowledgeRatingMayExceedFive(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        $data['kenntnisse'][0]['wert'] = 12;

        $errors = $validator->validate($data);

        $this->assertSame([], $errors);
    }

    public function testKnowledgeLabelFlagMayBeFalse(): void
    {
        $validator = new SchemaValidator($this->schemaPath());
        $data = $this->validData();
        $data['zeige_kenntnisse_label'] = false;

        $errors = $validator->validate($data);

        $this->assertSame([], $errors);
    }

    public function testContactDataUsesItsOwnSchema(): void
    {
        $validator = new SchemaValidator($this->contactSchemaPath());
        $errors = $validator->validate([
            'name' => 'Max Mustermann',
            'ort' => 'Berlin',
            'email' => 'max@example.com',
            'telefon' => '+49 123 456',
        ]);

        $this->assertSame([], $errors);
    }

    private function schemaPath(): string
    {
        return dirname(__DIR__, 2) . '/src/resources/build/schemas/lebenslauf.schema.json';
    }

    private function contactSchemaPath(): string
    {
        return dirname(__DIR__, 2) . '/src/resources/build/schemas/kontaktdaten.schema.json';
    }

    private function validData(): array
    {
        return [
            'zeige_kenntnisse_label' => true,
            'titel' => 'Softwareentwicklung',
            'motivation' => 'Kurzbeschreibung.',
            'kenntnisse' => [
                [
                    'gruppe_label' => 'Senior',
                    'wert' => 4,
                    'tags' => [
                        [
                            'de' => 'Datenbanken',
                            'en' => 'Databases',
                        ],
                        'SQL',
                    ],
                ],
            ],
            'berufserfahrung' => [
                [
                    'unternehmen' => 'Beispiel GmbH',
                    'stellen' => [
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
                        ],
                    ],
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
