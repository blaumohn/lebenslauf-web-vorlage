<?php

/*
 * Ported from symfony/http-foundation AcceptHeader.
 * Source: https://github.com/symfony/http-foundation/blob/main/AcceptHeader.php
 * To update: copy the source file and change the namespace below.
 */

namespace App\Http\Lang;

class AcceptHeader
{
    /** @var array<string, AcceptHeaderItem> */
    private array $items = [];

    private bool $sorted = true;

    /** @param AcceptHeaderItem[] $items */
    public function __construct(array $items)
    {
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    public static function fromString(?string $headerValue): self
    {
        $items = [];
        foreach (HeaderUtils::split($headerValue ?? '', ',;=') as $i => $parts) {
            $part = array_shift($parts);
            $item = new AcceptHeaderItem($part[0], HeaderUtils::combine($parts));

            $items[] = $item->setIndex($i);
        }

        return new self($items);
    }

    public function __toString(): string
    {
        return implode(',', $this->items);
    }

    public function has(string $value): bool
    {
        $canonicalKey = $this->getCanonicalKey(AcceptHeaderItem::fromString($value));

        return isset($this->items[$canonicalKey]);
    }

    public function get(string $value): ?AcceptHeaderItem
    {
        $queryItem = AcceptHeaderItem::fromString($value.';q=1');
        $canonicalKey = $this->getCanonicalKey($queryItem);

        if (isset($this->items[$canonicalKey])) {
            return $this->items[$canonicalKey];
        }

        if (!$candidates = array_filter($this->items, fn (AcceptHeaderItem $item) => $this->matches($item, $queryItem))) {
            return null;
        }

        usort(
            $candidates,
            fn ($a, $b) => $this->getSpecificity($b, $queryItem) <=> $this->getSpecificity($a, $queryItem)
                ?: $b->getQuality() <=> $a->getQuality()
                ?: $a->getIndex() <=> $b->getIndex()
        );

        return reset($candidates);
    }

    public function add(AcceptHeaderItem $item): static
    {
        $this->items[$this->getCanonicalKey($item)] = $item;
        $this->sorted = false;

        return $this;
    }

    /** @return AcceptHeaderItem[] */
    public function all(): array
    {
        $this->sort();

        return $this->items;
    }

    public function filter(string $pattern): self
    {
        return new self(array_filter($this->items, static fn ($item) => preg_match($pattern, $item->getValue())));
    }

    public function first(): ?AcceptHeaderItem
    {
        $this->sort();

        return $this->items ? reset($this->items) : null;
    }

    private function sort(): void
    {
        if (!$this->sorted) {
            uasort($this->items, static fn ($a, $b) => $b->getQuality() <=> $a->getQuality() ?: $a->getIndex() <=> $b->getIndex());

            $this->sorted = true;
        }
    }

    private function getCanonicalKey(AcceptHeaderItem $item): string
    {
        $parts = [];

        $attributes = $this->getMediaParams($item);
        ksort($attributes);

        foreach ($attributes as $name => $value) {
            if (null === $value) {
                $parts[] = $name;
                continue;
            }

            $quotedValue = \is_string($value) && preg_match('/[\s;,=]/', $value) ? '"'.addcslashes($value, '"\\').'"' : $value;

            $parts[] = $name.'='.$quotedValue;
        }

        return $item->getValue().($parts ? ';'.implode(';', $parts) : '');
    }

    private function matches(AcceptHeaderItem $rangeItem, AcceptHeaderItem $queryItem): bool
    {
        $rangeValue = strtolower($rangeItem->getValue());
        $queryValue = strtolower($queryItem->getValue());

        if ('*' === $rangeValue || '*/*' === $rangeValue) {
            return $this->rangeParametersMatch($rangeItem, $queryItem);
        }

        if ('*' === $queryValue) {
            return false;
        }

        $isQueryMedia = str_contains($queryValue, '/');
        $isRangeMedia = str_contains($rangeValue, '/');

        if ($isQueryMedia !== $isRangeMedia) {
            return false;
        }

        if (!$isQueryMedia) {
            return $rangeValue === $queryValue && $this->rangeParametersMatch($rangeItem, $queryItem);
        }

        [$queryType, $querySubtype] = explode('/', $queryValue, 2);
        [$rangeType, $rangeSubtype] = explode('/', $rangeValue, 2) + [1 => '*'];

        if ('*' !== $rangeType && $rangeType !== $queryType) {
            return false;
        }

        if ('*' !== $rangeSubtype && $rangeSubtype !== $querySubtype) {
            return false;
        }

        return $this->rangeParametersMatch($rangeItem, $queryItem);
    }

    private function rangeParametersMatch(AcceptHeaderItem $rangeItem, AcceptHeaderItem $queryItem): bool
    {
        $queryAttributes = $this->getMediaParams($queryItem);
        $rangeAttributes = $this->getMediaParams($rangeItem);

        foreach ($rangeAttributes as $name => $rangeValue) {
            if (!\array_key_exists($name, $queryAttributes)) {
                return false;
            }

            $queryValue = $queryAttributes[$name];

            if (null === $rangeValue) {
                return null === $queryValue;
            }

            if (null === $queryValue || strtolower($queryValue) !== strtolower($rangeValue)) {
                return false;
            }
        }

        return true;
    }

    private function getSpecificity(AcceptHeaderItem $item, AcceptHeaderItem $queryItem): int
    {
        $rangeValue = strtolower($item->getValue());
        $queryValue = strtolower($queryItem->getValue());

        $paramCount = \count($this->getMediaParams($item));

        $isQueryMedia = str_contains($queryValue, '/');
        $isRangeMedia = str_contains($rangeValue, '/');

        if (!$isQueryMedia && !$isRangeMedia) {
            return ('*' !== $rangeValue ? 2000 : 1000) + $paramCount;
        }

        [$rangeType, $rangeSubtype] = explode('/', $rangeValue, 2) + [1 => '*'];

        $specificity = match (true) {
            '*' !== $rangeSubtype => 3000,
            '*' !== $rangeType => 2000,
            default => 1000,
        };

        return $specificity + $paramCount;
    }

    private function getMediaParams(AcceptHeaderItem $item): array
    {
        $attributes = array_change_key_case($item->getAttributes(), \CASE_LOWER);
        unset($attributes['q']);

        return $attributes;
    }
}
