<?php
declare(strict_types=1);

namespace App\Model\Repositories;

interface IAttendanceRepository
{
    public function isAttending(int $userId, int $eventId): bool;

    public function attend(int $userId, int $eventId): void;

    public function leave(int $userId, int $eventId): void;

    /**
     * @return int[]
     */
    public function findEventIdsByUser(int $userId): array;

    /**
     * @return int[]
     */
    public function findUserIdsByEvent(int $eventId): array;

    public function countByEvent(int $eventId): int;

    /**
     * @return string[]
     */
    public function findAttendeeNamesByEvent(int $eventId): array;
}
