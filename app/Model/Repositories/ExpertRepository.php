<?php
declare(strict_types=1);

namespace App\Model\Repositories;

use App\Model\Entities\Expert;
use App\Model\Entities\Tag;
use Nette\Database\Connection;
use Throwable;

final class ExpertRepository implements IExpertRepository
{
    /** @var array<int, Expert> */
    private array $experts = [];

    public function __construct(
        private readonly Connection $db,
        private readonly ITagRepository $tagRepository,
    ) {
        $this->reload();
    }

    public function findAll(bool $activeOnly = false): array
    {
        return array_values(array_filter(
            $this->experts,
            static fn(Expert $expert): bool => !$activeOnly || $expert->active,
        ));
    }

    public function findById(int $id, bool $activeOnly = false): ?Expert
    {
        $expert = $this->experts[$id] ?? null;
        if ($expert === null || ($activeOnly && !$expert->active)) {
            return null;
        }

        return $expert;
    }

    public function save(Expert $expert): Expert
    {
        $this->db->query('START TRANSACTION');

        try {
            if ($expert->id === null) {
                $this->db->query(
                    'INSERT INTO experts',
                    [
                        'name' => $expert->name,
                        'institution' => $this->normalizeNullableString($expert->institution),
                        'residence' => $this->normalizeNullableString($expert->address),
                        'email' => $this->normalizeNullableString($expert->email),
                        'telephone' => $this->normalizeNullableString($expert->phone),
                        'active' => $expert->active,
                        'note' => $this->normalizeNullableString($expert->note),
                    ]
                );

                $expertId = $this->getLastInsertId();
            } else {
                $expertId = $expert->id;
                $this->db->query(
                    'UPDATE experts SET ? WHERE id = ?',
                    [
                        'name' => ($expert->degree != "") ? ($expert->degree . ". " . $expert->name) : $expert->name,
                        'institution' => $this->normalizeNullableString($expert->institution),
                        'residence' => $this->normalizeNullableString($expert->address),
                        'email' => $this->normalizeNullableString($expert->email),
                        'telephone' => $this->normalizeNullableString($expert->phone),
                        'active' => $expert->active,
                        'note' => $this->normalizeNullableString($expert->note),
                    ],
                    $expertId,
                );
            }

            $stored = new Expert(
                degree: $expert->degree,
                name: $expert->name,
                id: $expertId,
                institution: $this->normalizeNullableString($expert->institution),
                address: $this->normalizeNullableString($expert->address),
                email: $this->normalizeNullableString($expert->email),
                phone: $this->normalizeNullableString($expert->phone),
                note: $this->normalizeNullableString($expert->note),
                tags: array_values(array_unique($expert->tags)),
                active: $expert->active,
            );

            $this->syncTags($stored);

            $this->db->query('COMMIT');

            $this->experts[$expertId] = $stored;

            return $stored;
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }


    public function hide(int $id): void
    {
        $this->setActive($id, false);
    }

    public function show(int $id): void
    {
        $this->setActive($id, true);
    }


    private function reload(): void
    {
        $this->experts = [];
        $tagMap = $this->loadExpertTags();
        $expr = "/(.*)(\. )(.*)/";

        $data = $this->db->query('SELECT * FROM experts');
        foreach ($data as $row) {
            $id = (int) $row->id;
            $name = (string) $row->name;
            $degree = "";
            preg_match($expr, $name, $matches);
            if (count($matches) == 4) {
                $degree = $matches[1];
                $name = $matches[3];
            }

            $expert = new Expert(
                degree: $degree,
                name: $name,
                id: (int) $id,
                institution: $this->normalizeNullableString($row->institution ?? null),
                address: $this->normalizeNullableString($row->residence ?? null),
                email: $this->normalizeNullableString($row->email ?? null),
                phone: $this->normalizeNullableString($row->telephone ?? null),
                note: $this->normalizeNullableString($row->note ?? null),
                tags: $tagMap[(int) $id] ?? [],
                active: (bool) ($row->active ?? true),
            );
            $this->experts[$expert->id] = $expert;
        }
    }

    /**
     * @return array<int, string[]>
     */
    private function loadExpertTags(): array
    {
        $rows = $this->db->query(
            'SELECT et.expert_id, t.tag_name
             FROM expert_tags et
             INNER JOIN tags t ON t.id = et.tag_id
             WHERE t.tag_type = ? AND t.scope IN (?, ?)
             ORDER BY t.tag_name ASC',
            Tag::TYPE_AREA,
            Tag::SCOPE_EXPERT,
            Tag::SCOPE_BOTH,
        );

        $map = [];
        foreach ($rows as $row) {
            $expertId = (int) $row->expert_id;
            $tagName = trim((string) $row->tag_name);
            if ($tagName === '') {
                continue;
            }

            $map[$expertId] ??= [];
            $map[$expertId][] = $tagName;
        }

        foreach ($map as $expertId => $tags) {
            $map[$expertId] = array_values(array_unique($tags));
        }

        return $map;
    }

    private function syncTags(Expert $expert): void
    {
        if ($expert->id === null) {
            return;
        }

        $this->db->query('DELETE FROM expert_tags WHERE expert_id = ?', $expert->id);

        $tagIds = [];
        foreach (array_values(array_unique($expert->tags)) as $tagName) {
            $tagName = trim($tagName);
            if ($tagName === '') {
                continue;
            }

            $tag = $this->tagRepository->findActiveByName(
                $tagName,
                Tag::TYPE_AREA,
                [Tag::SCOPE_EXPERT],
            );

            if ($tag?->id !== null) {
                $tagIds[$tag->id] = $tag->id;
            }
        }

        foreach ($tagIds as $tagId) {
            $this->db->query(
                'INSERT INTO expert_tags',
                [
                    'expert_id' => $expert->id,
                    'tag_id' => $tagId,
                ]
            );
        }
    }

    private function setActive(int $id, bool $active): void
    {
        $this->db->query('UPDATE experts SET active = ? WHERE id = ?', $active ? 1 : 0, $id);

        if (!isset($this->experts[$id])) {
            return;
        }

        $expert = $this->experts[$id];
        $this->experts[$id] = new Expert(
            degree: $expert->degree,
            name: $expert->name,
            id: $expert->id,
            institution: $expert->institution,
            address: $expert->address,
            email: $expert->email,
            phone: $expert->phone,
            note: $expert->note,
            tags: $expert->tags,
            active: $active,
        );
    }

    private function getLastInsertId(): int
    {
        $row = $this->db->fetch('SELECT LAST_INSERT_ID() AS id');

        return isset($row->id) ? (int) $row->id : 0;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
