<?php

namespace App\Cli\Site;

final class LabelService
{
    private array $labels;
    private string $lang;

    public function __construct(array $labels, string $lang)
    {
        $this->labels = $labels;
        $this->lang = $lang;
    }

    public static function fromJsonFile(string $path, string $lang): self
    {
        $content = file_get_contents($path);
        $data = $content === false ? [] : json_decode($content, true);
        if (!is_array($data)) {
            $data = [];
        }
        return new self($data, $lang);
    }

    public function all(): array
    {
        return $this->resolve($this->labels);
    }

    private function resolve(array $node): array
    {
        $resolved = [];
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                if ($this->isIntlString($value)) {
                    $resolved[$key] = $this->pickLang($value);
                } else {
                    $resolved[$key] = $this->resolve($value);
                }
            } else {
                $resolved[$key] = $value;
            }
        }
        return $resolved;
    }

    private function isIntlString(array $value): bool
    {
        $hasString = false;
        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
            $hasString = true;
        }
        return $hasString;
    }

    private function pickLang(array $value): string
    {
        if (isset($value[$this->lang])) {
            return $value[$this->lang];
        }

        if (isset($value['de'])) {
            return $value['de'];
        }

        return (string) reset($value);
    }
}
