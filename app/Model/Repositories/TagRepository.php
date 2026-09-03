<?php
declare(strict_types=1);

namespace App\Model\Repositories;

use App\Model\Entities\Tag;
use Nette\Database\Connection;

final class TagRepository implements ITagRepository
{
    /** @var string[] */
    private const array ALLOWED_TYPES = [
        Tag::TYPE_AREA,
        Tag::TYPE_CATEGORY,
    ];

    /** @var string[] */
    private const array ALLOWED_SCOPES = [
        Tag::SCOPE_EVENT,
        Tag::SCOPE_EXPERT,
        Tag::SCOPE_BOTH,
    ];

    /** @var array<int, Tag> */
    private array $tags = [];

    public function __construct(
        private readonly Connection $db,
    ) {
        foreach ($this->db->query('SELECT * FROM tags ORDER BY tag_name ASC') as $row) {
            $tagId = isset($row->id) ? (int) $row->id : null;
            if ($tagId === null) {
                continue;
            }

            $name = trim((string) ($row->tag_name ?? ''));
            if ($name === '') {
                continue;
            }

            $this->tags[$tagId] = new Tag(
                name: $name,
                slug: (string) ($row->slug ?? Tag::slugify($name)),
                type: (string) ($row->tag_type ?? Tag::TYPE_AREA),
                scope: (string) ($row->scope ?? Tag::SCOPE_BOTH),
                isActive: (bool) ($row->is_active ?? true),
                id: $tagId,
            );
        }
    }

    public function findAll(bool $activeOnly = false): array
    {
        return array_values(array_filter(
            $this->tags,
            static fn(Tag $tag): bool => !$activeOnly || $tag->isActive
        ));
    }

    public function findByType(string $type, array $scopes = [], bool $activeOnly = false): array
    {
        return array_values(array_filter(
            $this->tags,
            fn(Tag $tag): bool => $tag->type === $type
                && (!$activeOnly || $tag->isActive)
                && $this->matchesScope($tag, $scopes)
        ));
    }

    public function findActiveByType(string $type, array $scopes = []): array
    {
        return $this->findByType($type, $scopes, true);
    }

    public function findById(int $id): ?Tag
    {
        return $this->tags[$id] ?? null;
    }

    public function findActiveByName(string $name, ?string $type = null, array $scopes = []): ?Tag
    {
        $slug = Tag::slugify($name);

        foreach ($this->tags as $tag) {
            if (!$tag->isActive) {
                continue;
            }

            if ($type !== null && $tag->type !== $type) {
                continue;
            }

            if (!$this->matchesScope($tag, $scopes)) {
                continue;
            }

            if ($tag->slug === $slug) {
                return $tag;
            }
        }

        return null;
    }

    public function save(Tag $tag): Tag
    {
        $name = trim($tag->name);
        $slug = Tag::slugify($tag->slug !== '' ? $tag->slug : $name);
        $type = in_array($tag->type, self::ALLOWED_TYPES, true) ? $tag->type : Tag::TYPE_AREA;
        $scope = in_array($tag->scope, self::ALLOWED_SCOPES, true) ? $tag->scope : Tag::SCOPE_BOTH;

        if ($name === '' || $slug === '') {
            throw new \InvalidArgumentException('Tag musí mít platný název.');
        }

        if ($type === Tag::TYPE_CATEGORY && $scope !== Tag::SCOPE_EVENT) {
            throw new \InvalidArgumentException('Kategorie lze používat jen u akcí.');
        }

        $duplicate = $this->db->fetch('SELECT id FROM tags WHERE slug = ? LIMIT 1', $slug);
        if ($duplicate !== null && (int) $duplicate->id !== $tag->id) {
            throw new \InvalidArgumentException('Tag se stejným názvem už existuje.');
        }

        $payload = [
            'tag_name' => $name,
            'slug' => $slug,
            'tag_type' => $type,
            'scope' => $scope,
            'is_active' => $tag->isActive ? 1 : 0,
        ];

        if ($tag->id === null) {
            $this->db->query('INSERT INTO tags', $payload);
            $tagId = $this->getLastInsertId();
        } else {
            $tagId = $tag->id;
            $this->db->query('UPDATE tags SET ? WHERE id = ?', $payload, $tagId);
        }

        $stored = new Tag(
            name: $name,
            slug: $slug,
            type: $type,
            scope: $scope,
            isActive: $tag->isActive,
            id: $tagId,
        );

        $this->tags[$tagId] = $stored;
        uasort($this->tags, static fn(Tag $a, Tag $b): int => strcasecmp($a->name, $b->name));

        return $stored;
    }



    public function setActive(int $id, bool $active): void
    {
        $this->db->query('UPDATE tags SET is_active = ? WHERE id = ?', $active ? 1 : 0, $id);

        if (isset($this->tags[$id])) {
            $this->tags[$id] = new Tag(
                name: $this->tags[$id]->name,
                slug: $this->tags[$id]->slug,
                type: $this->tags[$id]->type,
                scope: $this->tags[$id]->scope,
                isActive: $active,
                id: $this->tags[$id]->id,
            );
        }
    }

    public function getUsageCounts(int $id): array
    {
        $events = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM event_tags WHERE tag_id = ?',
            $id,
        );
        $experts = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM expert_tags WHERE tag_id = ?',
            $id,
        );

        return [
            'events' => $events !== null ? (int) $events->cnt : 0,
            'experts' => $experts !== null ? (int) $experts->cnt : 0,
        ];
    }

    /**
     * @param string[] $scopes
     */
    private function matchesScope(Tag $tag, array $scopes): bool
    {
        if ($scopes === []) {
            return true;
        }

        if ($tag->scope === Tag::SCOPE_BOTH) {
            return true;
        }

        return in_array($tag->scope, $scopes, true);
    }

    private function getLastInsertId(): int
    {
        $row = $this->db->fetch('SELECT LAST_INSERT_ID() AS id');

        return isset($row->id) ? (int) $row->id : 0;
    }
}
