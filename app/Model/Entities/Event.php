<?php
declare(strict_types=1);

namespace App\Model\Entities;

use DateTimeImmutable;

class Event
{
    public const string STATUS_SUGGESTED = 'suggested';
    public const string STATUS_APPROVED = 'approved';
    public const string STATUS_REJECTED = 'rejected';

    /** @var string[] */
    private const array ALL_AUDIENCE_ROLES = [
        'student',
        'absolvent',
        'zamestnanec',
        'stredoskolak',
        'jine',
    ];

    public function __construct(
        public string $name,
        public string $description,
        public string $organiser,
        public readonly ?int $id = null,
        public ?int $organiserId = null,
        public string $status = self::STATUS_SUGGESTED,
        public ?DateTimeImmutable $date = null,
        public ?string $imagePath = null,
        public ?string $filePath = null,
        public ?string $eventReportPath = null,
        public ?string $audienceTag = null,
        /** @var string[] */
        public array $audienceRoles = [],
        /** @var string[] */
        public array $areaTags = [],
        public ?string $categoryTag = null,
        public ?int $expertId = null,
        /** @var string[] */
        public array $tags = [],
    ) {
    }

    /**
     * @return string[]
     */
    public function getAudienceRoles(): array
    {
        if ($this->audienceRoles !== []) {
            return array_values(array_unique(array_filter(array_map(
                static fn(string $role): ?string => self::normalizeAudienceValue($role),
                $this->audienceRoles
            ))));
        }

        if ($this->audienceTag !== null && $this->audienceTag !== '') {
            $normalized = self::normalizeAudienceValue($this->audienceTag);

            return $normalized !== null ? [$normalized] : [];
        }

        $roles = [];
        foreach ($this->tags as $tag) {
            $normalized = self::normalizeAudienceValue($tag);
            if ($normalized === null) {
                continue;
            }

            if ($normalized === 'vsechny') {
                return self::ALL_AUDIENCE_ROLES;
            }

            $roles[$normalized] = $normalized;
        }

        return array_values($roles);
    }

    public function getAudienceTag(): string
    {
        $roles = $this->getAudienceRoles();
        if ($roles === []) {
            return 'Všechny';
        }

        return self::formatAudienceLabel(reset($roles));
    }

    public function getAudienceSummary(): string
    {
        $labels = array_map(
            static fn(string $role): string => self::formatAudienceLabel($role),
            $this->getAudienceRoles()
        );

        return $labels !== [] ? implode(', ', $labels) : 'Všechny';
    }

    /**
     * @return string[]
     */
    public function getAreaTags(): array
    {
        if ($this->areaTags !== []) {
            return array_values(array_map(
                static fn(string $tag): string => self::formatAreaLabel($tag),
                $this->areaTags
            ));
        }

        $areaTags = array_filter(
            $this->tags,
            static fn(string $tag): bool => !self::isAudienceValue($tag) && !self::isCategoryValue($tag)
        );

        return array_values(array_map(
            static fn(string $tag): string => self::formatAreaLabel($tag),
            $areaTags
        ));
    }

    /**
     * @return string[]
     */
    public function getPublicTagNames(): array
    {
        $publicTags = [];

        $category = $this->getCategoryTag();
        if ($category !== null && $category !== '') {
            $publicTags[] = $category;
        }

        foreach ($this->areaTags as $tag) {
            $tag = trim($tag);
            if ($tag !== '') {
                $publicTags[] = $tag;
            }
        }

        if ($publicTags !== []) {
            return array_values(array_unique($publicTags));
        }

        return array_values(array_unique(array_filter(
            $this->tags,
            static fn(string $tag): bool => !self::isAudienceValue($tag) && !self::isCategoryValue($tag)
        )));
    }

    public function getCategoryTag(): ?string
    {
        if ($this->categoryTag !== null && $this->categoryTag !== '') {
            return $this->categoryTag;
        }

        foreach ($this->tags as $tag) {
            if (self::isCategoryValue($tag)) {
                return self::formatCategoryLabel($tag);
            }
        }

        return null;
    }

    /**
     * @return array<int, array{label: string, type: string}>
     */
    public function getDisplayTags(): array
    {
        $tags = [];

        foreach ($this->getAudienceRoles() as $role) {
            $tags[] = [
                'label' => self::formatAudienceLabel($role),
                'type' => 'audience',
            ];
        }

        $category = $this->getCategoryTag();
        if ($category !== null && $category !== '') {
            $tags[] = [
                'label' => self::formatCategoryLabel($category),
                'type' => 'category',
            ];
        }

        foreach ($this->getAreaTags() as $tag) {
            $tags[] = [
                'label' => $tag,
                'type' => 'area',
            ];
        }

        return $tags;
    }

    /**
     * @return array<int, array{label: string, type: string}>
     */
    public function getPublicDisplayTags(): array
    {
        return array_values(array_filter(
            $this->getDisplayTags(),
            static fn(array $tag): bool => $tag['type'] !== 'audience'
        ));
    }

    /**
     * @return string[]
     */
    public function getFilterTags(): array
    {
        return array_values(array_unique($this->getPublicTagNames()));
    }

    public function canUserAttend(?string $userRole): bool
    {
        if ($userRole === null || $userRole === '') {
            return false;
        }

        $normalizedUserRole = self::normalizeAudienceValue($userRole);
        if ($normalizedUserRole === null || $normalizedUserRole === 'vsechny') {
            return false;
        }

        return in_array($normalizedUserRole, $this->getAudienceRoles(), true);
    }

    public static function formatAudienceLabel(string $value): string
    {
        return match (self::normalizeAudienceValue($value)) {
            'student' => 'Student ZČU',
            'absolvent' => 'Absolvent ZČU',
            'zamestnanec' => 'Zaměstnanec ZČU',
            'stredoskolak' => 'Žák SŠ',
            'jine' => 'Ostatní',
            'vsechny' => 'Všechny',
            default => $value,
        };
    }

    public static function formatCategoryLabel(string $value): string
    {
        return match (self::normalizeTagValue($value)) {
            'verejne' => 'Veřejné',
            'komunitni' => 'Komunitní',
            'univerzitni' => 'Univerzitní',
            default => $value,
        };
    }

    public static function formatAreaLabel(string $value): string
    {
        return match (self::normalizeTagValue($value)) {
            'ai' => 'AI',
            'technologie' => 'Technologie',
            'sport' => 'Sport',
            'kultura' => 'Kultura',
            'veda-a-vyzkum' => 'Věda a výzkum',
            'prednaska' => 'Přednáška',
            'workshop' => 'Workshop',
            'networking' => 'Networking',
            default => $value,
        };
    }

    private static function isAudienceValue(string $value): bool
    {
        return self::normalizeAudienceValue($value) !== null;
    }

    private static function normalizeAudienceValue(string $value): ?string
    {
        return match (self::normalizeTagValue($value)) {
            'student', 'student-zcu' => 'student',
            'absolvent', 'absolvent-zcu' => 'absolvent',
            'zamestnanec', 'zamestnanec-zcu' => 'zamestnanec',
            'stredoskolak', 'zak-ss' => 'stredoskolak',
            'jine', 'ostatni' => 'jine',
            'vsechny' => 'vsechny',
            default => null,
        };
    }

    private static function isCategoryValue(string $value): bool
    {
        return in_array(self::normalizeTagValue($value), [
            'verejne',
            'komunitni',
            'univerzitni',
        ], true);
    }

    private static function normalizeTagValue(string $value): string
    {
        $normalized = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value)
            : $value;

        $normalized = strtolower((string) $normalized);
        $normalized = preg_replace('~[^a-z0-9]+~', '-', $normalized) ?? $normalized;

        return trim($normalized, '-');
    }
}
