<?php
declare(strict_types=1);

namespace App\Model\Repositories;

use Nette\Database\Connection;

final class EventAudienceRepository implements IEventAudienceRepository
{
    /** @var string[] */
    private const array ALLOWED_ROLES = [
        'student',
        'absolvent',
        'zamestnanec',
        'stredoskolak',
        'jine',
    ];

    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findRolesByEvent(int $eventId): array
    {
        if (!$this->hasTable()) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT role FROM event_audience_roles WHERE event_id = ? ORDER BY role ASC',
            $eventId,
        );

        $roles = [];
        foreach ($rows as $row) {
            $role = $this->normalizeRole((string) $row->role);
            if ($role !== null) {
                $roles[$role] = $role;
            }
        }

        return array_values($roles);
    }

    public function replaceRolesForEvent(int $eventId, array $roles): void
    {
        if (!$this->hasTable()) {
            return;
        }

        $normalizedRoles = [];
        foreach ($roles as $role) {
            $normalizedRole = $this->normalizeRole((string) $role);
            if ($normalizedRole !== null) {
                $normalizedRoles[$normalizedRole] = $normalizedRole;
            }
        }

        $this->db->query('DELETE FROM event_audience_roles WHERE event_id = ?', $eventId);

        foreach (array_values($normalizedRoles) as $role) {
            $this->db->query(
                'INSERT INTO event_audience_roles',
                [
                    'event_id' => $eventId,
                    'role' => $role,
                ]
            );
        }
    }

    public function canRoleAttend(int $eventId, string $role): bool
    {
        if (!$this->hasTable()) {
            return false;
        }

        $normalizedRole = $this->normalizeRole($role);
        if ($normalizedRole === null) {
            return false;
        }

        return $this->db->fetch(
            'SELECT 1 FROM event_audience_roles WHERE event_id = ? AND role = ? LIMIT 1',
            $eventId,
            $normalizedRole,
        ) !== null;
    }

    public function hasAnyRoles(int $eventId): bool
    {
        if (!$this->hasTable()) {
            return false;
        }

        return $this->db->fetch(
            'SELECT 1 FROM event_audience_roles WHERE event_id = ? LIMIT 1',
            $eventId,
        ) !== null;
    }

    public function deleteForEvent(int $eventId): void
    {
        if (!$this->hasTable()) {
            return;
        }

        $this->db->query('DELETE FROM event_audience_roles WHERE event_id = ?', $eventId);
    }

    private function normalizeRole(string $role): ?string
    {
        $normalized = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $role)
            : $role;

        $normalized = strtolower((string) $normalized);
        $normalized = preg_replace('~[^a-z0-9]+~', '-', $normalized) ?? $normalized;
        $normalized = trim($normalized, '-');

        $normalized = match ($normalized) {
            'student-zcu' => 'student',
            'absolvent-zcu' => 'absolvent',
            'zamestnanec-zcu' => 'zamestnanec',
            'zak-ss' => 'stredoskolak',
            'ostatni' => 'jine',
            default => $normalized,
        };

        return in_array($normalized, self::ALLOWED_ROLES, true) ? $normalized : null;
    }

    private function hasTable(): bool
    {
        static $hasTable;

        if ($hasTable === null) {
            $hasTable = $this->db->fetch('SHOW TABLES LIKE ?', 'event_audience_roles') !== null;
        }

        return $hasTable;
    }
}
