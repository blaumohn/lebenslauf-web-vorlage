<?php

namespace App\Http\Cv;

final class CvViewModelBuilder
{
    public function build(array $cv): array
    {
        $cv['berufserfahrung'] = $this->buildEntries($cv['berufserfahrung']);
        if (isset($cv['opensource']) && is_array($cv['opensource'])) {
            $cv['opensource'] = $this->buildEntries($cv['opensource']);
        }
        $cv['faehigkeiten'] = $this->buildSkills($cv['faehigkeiten']);

        return $cv;
    }

    private function buildEntries(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $unternehmen = $this->stringOrNull($entry['unternehmen'] ?? null);
            $projekt = $this->stringOrNull($entry['projekt'] ?? null);
            $projektUrl = $this->stringOrNull($entry['url'] ?? null);
            $stelleGruppe = is_array($entry['stelleGruppe'] ?? null) ? $entry['stelleGruppe'] : null;
            $isGrouped = $stelleGruppe !== null;
            $showCompany = $unternehmen !== null && (!$isGrouped || !empty($stelleGruppe['letzteStelle']));
            $companyLine = $showCompany ? $unternehmen : null;
            $projectLine = $unternehmen === null && $projekt !== null ? $projekt : null;

            $titel = (string) $entry['titel'];
            $headerTitle = $unternehmen !== null && $projekt !== null
                ? $projekt . ' | ' . $titel
                : $titel;

            $zeitraum = (string) $entry['zeitraum'];
            $ort = $this->stringOrNull($entry['ort'] ?? null);

            $punkte = $this->buildPoints($entry['punkte']);

            $result[] = [
                'show_company' => $companyLine !== null,
                'company_line' => $companyLine,
                'show_project' => $projectLine !== null,
                'project_line' => $projectLine,
                'project_url' => $projectLine !== null ? $projektUrl : null,
                'grouped' => $isGrouped,
                'header_title' => $headerTitle,
                'header_time' => $zeitraum,
                'show_location' => $ort !== null && $ort !== '',
                'location' => $ort,
                'punkte' => $punkte,
            ];
        }

        return $result;
    }

    private function buildPoints(array $punkte): array
    {
        $result = [];
        foreach ($punkte as $punkt) {
            if (!is_array($punkt)) {
                continue;
            }

            $tags = is_array($punkt['tags'] ?? null) ? $punkt['tags'] : [];
            $result[] = [
                'text' => (string) ($punkt['text'] ?? ''),
                'tags' => $tags,
                'show_tags' => count($tags) > 0,
            ];
        }

        return $result;
    }

    private function buildSkills(array $groups): array
    {
        $result = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $technologien = is_array($group['technologien'] ?? null) ? $group['technologien'] : [];
            $result[] = [
                'stufe' => (string) ($group['stufe'] ?? ''),
                'wert' => (int) ($group['wert'] ?? 0),
                'technologien' => $technologien,
                'show_technologien' => count($technologien) > 0,
            ];
        }

        return $result;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
