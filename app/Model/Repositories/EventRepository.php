<?php
declare(strict_types=1);

namespace App\Model\Repositories;

use App\Model\Entities\Event;
use App\Model\Entities\Tag;
use DateTimeImmutable;
use Nette\Database\Connection;

final class EventRepository implements IEventRepository
{
    public function __construct(
        private readonly Connection $db,
        private readonly IEventAudienceRepository $eventAudienceRepository,
        private readonly ITagRepository $tagRepository,
    ) {
    }

    public function findAll(): array
    {
        return array_map(
            fn(array $row): Event => $this->hydrateEvent($row),
            array_values($this->loadAll())
        );
    }

    public function findApproved(): array
    {
        return array_map(
            fn(array $row): Event => $this->hydrateEvent($row),
            array_values($this->loadApproved())
        );
    }

    public function findById(int $id): ?Event
    {
        $row = $this->db->fetch('SELECT * FROM events WHERE id = ? LIMIT 1', $id);

        return $row !== null ? $this->hydrateEvent((array) $row) : null;
    }

    public function save(Event $event): Event
    {
        $payload = $this->extractEvent($event);

        if ($event->id === null) {
            $this->db->query('INSERT INTO events', $payload);
            $eventId = $this->getLastInsertId();
        } else {
            $eventId = $event->id;
            $this->db->query('UPDATE events SET ? WHERE id = ?', $payload, $eventId);
        }

        return new Event(
            name: $event->name,
            description: $event->description,
            organiser: $event->organiser,
            id: $eventId,
            organiserId: $event->organiserId,
            status: $event->status,
            date: $event->date,
            imagePath: $event->imagePath,
            filePath: $event->filePath,
            eventReportPath: $event->eventReportPath,
            audienceTag: $event->audienceTag,
            audienceRoles: $event->getAudienceRoles(),
            areaTags: $event->getAreaTags(),
            categoryTag: $event->getCategoryTag(),
            expertId: $event->expertId,
            tags: $event->getPublicTagNames(),
        );
    }


    public function hide(int $id): void
    {
        $event = $this->findById($id);
        if ($event === null) {
            return;
        }

        $event->status = Event::STATUS_REJECTED;
        $this->save($event);
    }

    public function syncTagAssignments(Event $event): void
    {
        if ($event->id === null) {
            return;
        }

        $this->db->query('DELETE FROM event_tags WHERE event_id = ?', $event->id);

        $tagIds = [];

        $categoryTag = $event->getCategoryTag();
        if ($categoryTag !== null && $categoryTag !== '') {
            $tag = $this->tagRepository->findActiveByName(
                $categoryTag,
                Tag::TYPE_CATEGORY,
                [Tag::SCOPE_EVENT],
            );

            if ($tag?->id !== null) {
                $tagIds[$tag->id] = $tag->id;
            }
        }

        foreach ($event->getAreaTags() as $tagName) {
            $tagName = trim($tagName);
            if ($tagName === '') {
                continue;
            }

            $tag = $this->tagRepository->findActiveByName(
                $tagName,
                Tag::TYPE_AREA,
                [Tag::SCOPE_EVENT],
            );

            if ($tag?->id !== null) {
                $tagIds[$tag->id] = $tag->id;
            }
        }

        foreach ($tagIds as $tagId) {
            $this->db->query(
                'INSERT INTO event_tags',
                [
                    'event_id' => $event->id,
                    'tag_id' => $tagId,
                ]
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadAll(): array
    {
        return $this->db->query('SELECT * FROM events')->fetchAssoc('id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadApproved(): array
    {
        return $this->db->query(
            'SELECT * FROM events WHERE status = ? ORDER BY datetime ASC',
            'approved',
        )->fetchAssoc('id');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateEvent(array $row): Event
    {
        $publicTags = $this->loadPublicTags((int) $row['id']);
        $audienceRoles = $this->eventAudienceRepository->findRolesByEvent((int) $row['id']);

        $categoryTag = null;
        $areaTags = [];
        $allTagNames = [];

        foreach ($publicTags as $publicTag) {
            $name = $publicTag['name'];
            $allTagNames[] = $name;

            if ($publicTag['type'] === Tag::TYPE_CATEGORY) {
                $categoryTag ??= $name;
                continue;
            }

            if ($publicTag['type'] === Tag::TYPE_AREA) {
                $areaTags[] = $name;
            }
        }

        return new Event(
            name: (string) $row['name'],
            description: (string) ($row['description'] ?? ''),
            organiser: (string) ($row['organiser'] ?? 'Lumio'),
            id: isset($row['id']) ? (int) $row['id'] : null,
            organiserId: isset($row['organiser_id']) && $row['organiser_id'] !== null ? (int) $row['organiser_id'] : null,
            status: $this->hydrateStatus((string) ($row['status'] ?? 'pending')),
            date: !empty($row['datetime']) ? new DateTimeImmutable((string) $row['datetime']) : null,
            imagePath: $row['image_path'] !== null && $row['image_path'] !== '' ? (string) $row['image_path'] : null,
            filePath: $row['file_path'] !== null && $row['file_path'] !== '' ? (string) $row['file_path'] : null,
            eventReportPath: $row['event_report_path'] !== null && $row['event_report_path'] !== '' ? (string) $row['event_report_path'] : null,
            audienceRoles: $audienceRoles,
            areaTags: array_values(array_unique($areaTags)),
            categoryTag: $categoryTag,
            expertId: isset($row['expert_id']) && $row['expert_id'] !== null ? (int) $row['expert_id'] : null,
            tags: array_values(array_unique($allTagNames)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function extractEvent(Event $event): array
    {
        $data = [
            'status' => $this->extractStatus($event->status),
            'name' => $event->name,
            'description' => $event->description,
            'datetime' => $event->date?->format('Y-m-d H:i:s'),
            'image_path' => $event->imagePath,
            'organiser' => $event->organiser,
            'organiser_id' => $event->organiserId,
            'file_path' => $event->filePath,
            'event_report_path' => $event->eventReportPath,
            'expert_id' => $event->expertId,
        ];

        if ($event->id !== null) {
            $data['id'] = $event->id;
        }

        return $data;
    }

    /**
     * @return array<int, array{name: string, type: string}>
     */
    private function loadPublicTags(int $eventId): array
    {
        $rows = $this->db->query(
            'SELECT t.tag_name, t.tag_type
             FROM event_tags et
             INNER JOIN tags t ON t.id = et.tag_id
             WHERE et.event_id = ?
             ORDER BY CASE WHEN t.tag_type = ? THEN 0 ELSE 1 END, t.tag_name ASC',
            $eventId,
            Tag::TYPE_CATEGORY,
        );

        $tags = [];
        foreach ($rows as $row) {
            $tagName = trim((string) $row->tag_name);
            if ($tagName === '') {
                continue;
            }

            $key = Tag::slugify($tagName) . '|' . (string) $row->tag_type;
            $tags[$key] = [
                'name' => $tagName,
                'type' => (string) $row->tag_type,
            ];
        }

        return array_values($tags);
    }

    private function extractStatus(string $status): string
    {
        return $status === Event::STATUS_SUGGESTED ? 'pending' : $status;
    }

    private function hydrateStatus(string $status): string
    {
        return $status === 'pending' ? Event::STATUS_SUGGESTED : $status;
    }

    private function getLastInsertId(): int
    {
        $row = $this->db->fetch('SELECT LAST_INSERT_ID() AS id');

        return isset($row->id) ? (int) $row->id : 0;
    }
}
