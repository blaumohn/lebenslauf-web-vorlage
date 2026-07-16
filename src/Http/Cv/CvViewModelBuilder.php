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
        $cv['kenntnisse'] = $this->buildSkills($cv['kenntnisse']);

        return $cv;
    }

    private function buildEmploymentGroups(array $groups): array
    {
        $result = [];
        foreach ($groups as $group) {
            if (array_key_exists('station', $group)) {
                $result[] = $this->buildStation($group);
                continue;
            }

            if (array_key_exists('unternehmen', $group)) {
                $result[] = $this->buildCompanyGroup($group);
                continue;
            }

            throw new \LogicException('Berufserfahrung braucht station oder unternehmen.');
        }

        return $result;
    }

    private function buildCompanyGroup(array $group): array
    {
        return [
            'type' => 'company',
            'company' => (string) $group['unternehmen'],
            'positions' => $this->buildPositions($group['stellen']),
        ];
    }

    private function buildStation(array $station): array
    {
        $ort = $this->stringOrNull($station['ort'] ?? null);

        return [
            'type' => 'station',
            'show_project' => false,
            'project_line' => null,
            'project_url' => null,
            'header_title' => (string) $station['station'],
            'header_time' => (string) $station['zeitraum'],
            'show_location' => $ort !== null,
            'location' => $ort,
            'beschreibung' => (string) $station['beschreibung'],
        ];
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
        $maxWert = $this->highestSkillValue($groups);
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $tags = is_array($group['tags'] ?? null) ? $group['tags'] : [];
            $skill = [
                'gruppe_label' => (string) ($group['gruppe_label'] ?? ''),
                'show_wert' => false,
                'tags' => $tags,
                'show_tags' => count($tags) > 0,
            ];
            if (array_key_exists('wert', $group)) {
                $skill['wert'] = (int) $group['wert'];
                $skill['max_wert'] = $maxWert;
                $skill['show_wert'] = true;
            }
            $result[] = $skill;
        }

        return $result;
    }

    private function highestSkillValue(array $groups): int
    {
        $maxWert = 0;
        foreach ($groups as $group) {
            if (!is_array($group) || !array_key_exists('wert', $group)) {
                continue;
            }

            $maxWert = max($maxWert, (int) $group['wert']);
        }

        return $maxWert;
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
