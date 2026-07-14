<?php

namespace App\Http\Cv;

final class CvViewModelBuilder
{
    public function build(array $cv): array
    {
        $cv['berufserfahrung'] = $this->buildEmploymentGroups($cv['berufserfahrung']);
        if (isset($cv['opensource']) && is_array($cv['opensource'])) {
            $cv['opensource'] = $this->buildProjectEntries($cv['opensource']);
        }
        $cv['faehigkeiten'] = $this->buildSkills($cv['faehigkeiten']);

        return $cv;
    }

    private function buildEmploymentGroups(array $groups): array
    {
        $result = [];
        foreach ($groups as $group) {
            $positions = $this->buildPositions($group['stellen']);
            $result[] = [
                'company' => (string) $group['unternehmen'],
                'positions' => $positions,
            ];
        }

        return $result;
    }

    private function buildPositions(array $positions): array
    {
        $result = [];
        foreach ($positions as $position) {
            $ort = $this->stringOrNull($position['ort']);
            $result[] = [
                'show_project' => false,
                'project_line' => null,
                'project_url' => null,
                'header_title' => (string) $position['titel'],
                'header_time' => (string) $position['zeitraum'],
                'show_location' => $ort !== null,
                'location' => $ort,
                'punkte' => $this->buildPoints($position['punkte']),
            ];
        }

        return $result;
    }

    private function buildProjectEntries(array $entries): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $projektUrl = $this->stringOrNull($entry['url'] ?? null);
            $result[] = [
                'show_project' => true,
                'project_line' => (string) $entry['projekt'],
                'project_url' => $projektUrl,
                'header_title' => (string) $entry['titel'],
                'header_time' => (string) $entry['zeitraum'],
                'show_location' => false,
                'location' => null,
                'punkte' => $this->buildPoints($entry['punkte']),
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
