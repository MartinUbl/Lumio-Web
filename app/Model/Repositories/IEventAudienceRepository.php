<?php
declare(strict_types=1);

namespace App\Model\Repositories;

interface IEventAudienceRepository
{
    /**
     * @return string[]
     */
    public function findRolesByEvent(int $eventId): array;

    /**
     * @param string[] $roles
     */
    public function replaceRolesForEvent(int $eventId, array $roles): void;

    public function canRoleAttend(int $eventId, string $role): bool;

    public function hasAnyRoles(int $eventId): bool;

    public function deleteForEvent(int $eventId): void;
}
