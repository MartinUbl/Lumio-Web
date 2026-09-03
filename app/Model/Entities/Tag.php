<?php
declare(strict_types=1);

namespace App\Model\Entities;

class Tag
{
    public const string TYPE_CATEGORY = 'category';
    public const string TYPE_AREA = 'area';
    public const string SCOPE_EVENT = 'event';
    public const string SCOPE_EXPERT = 'expert';
    public const string SCOPE_BOTH = 'both';

    public function __construct(
        public string $name,
        public string $slug,
        public string $type = self::TYPE_AREA,
        public string $scope = self::SCOPE_BOTH,
        public bool $isActive = true,
        public readonly ?int $id = null,
    ) {
    }

    public static function slugify(string $value): string
    {
        $normalized = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value)
            : $value;

        $normalized = strtolower((string) $normalized);
        $normalized = preg_replace('~[^a-z0-9]+~', '-', $normalized) ?? $normalized;

        return trim($normalized, '-');
    }
}
