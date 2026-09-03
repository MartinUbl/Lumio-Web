<?php declare(strict_types=1);

namespace App\Model\Repositories;

use App\Model\Entities\Tag;

interface ITagRepository
{
    /** @return Tag[] */
    public function findAll(bool $activeOnly = false): array;

    /** @return Tag[] */
    public function findByType(string $type, array $scopes = [], bool $activeOnly = false): array;

    /** @return Tag[] */
    public function findActiveByType(string $type, array $scopes = []): array;

    public function findById(int $id): ?Tag;

    public function findActiveByName(string $name, ?string $type = null, array $scopes = []): ?Tag;

    public function save(Tag $tag): Tag;

    public function setActive(int $id, bool $active): void;

    /** @return array{events: int, experts: int} */
    public function getUsageCounts(int $id): array;
}
