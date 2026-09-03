<?php
declare(strict_types=1);

namespace App\Model\Repositories;

use Nette\Database\Connection;

final class AttendanceRepository implements IAttendanceRepository
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function isAttending(int $userId, int $eventId): bool
    {
        return $this->db->fetch(
            'SELECT 1 FROM attendees WHERE users_id = ? AND events_id = ? LIMIT 1',
            $userId,
            $eventId,
        ) !== null;
    }

    public function attend(int $userId, int $eventId): void
    {
        if ($this->isAttending($userId, $eventId)) {
            return;
        }

        $this->db->query(
            'INSERT INTO attendees',
            [
                'users_id' => $userId,
                'events_id' => $eventId,
            ]
        );
    }

    public function leave(int $userId, int $eventId): void
    {
        $this->db->query(
            'DELETE FROM attendees WHERE users_id = ? AND events_id = ?',
            $userId,
            $eventId,
        );
    }

    public function findEventIdsByUser(int $userId): array
    {
        $rows = $this->db->query(
            'SELECT events_id FROM attendees WHERE users_id = ? ORDER BY id DESC',
            $userId,
        );

        $eventIds = [];
        foreach ($rows as $row) {
            $eventIds[] = (int) $row->events_id;
        }

        return $eventIds;
    }

    public function findUserIdsByEvent(int $eventId): array
    {
        $rows = $this->db->query(
            'SELECT users_id FROM attendees WHERE events_id = ? ORDER BY id DESC',
            $eventId,
        );

        $userIds = [];
        foreach ($rows as $row) {
            $userIds[] = (int) $row->users_id;
        }

        return $userIds;
    }

    public function countByEvent(int $eventId): int
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM attendees WHERE events_id = ?',
            $eventId,
        );

        return $row !== null ? (int) $row->cnt : 0;
    }

    public function findAttendeeNamesByEvent(int $eventId): array
    {
        $rows = $this->db->query(
            'SELECT u.name
             FROM attendees a
             INNER JOIN users u ON u.id = a.users_id
             WHERE a.events_id = ?
             ORDER BY u.name ASC',
            $eventId,
        );

        $names = [];
        foreach ($rows as $row) {
            $name = trim((string) $row->name);
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }
}
