<?php

namespace App\Model\Repositories;

use App\Model\Entities\User;
use Nette\Database\Connection;
use Nette\Security\Passwords;

class UserRepository implements IUserRepository
{
    /** @var array<int, User> */
    private array $users = [];

    public function __construct(private Connection $db, private Passwords $passwords)
    {
        $data = $db->query('SELECT * FROM users');

        foreach ($data as $row) {
            $user = new User(
                name: (string) $row->name,
                email: $this->normalizeEmail((string) $row->email),
                passwordHash: (string) $row->password,
                id: (int) $row->id,
                role: (string) $row->role,
                faculty: $this->normalizeFaculty($row->faculty),
                active: (bool) $row->active,
                admin: (bool) $row->admin,
            );
            $this->users[$user->id] = $user;
        }
    }

    public function findAll(): array
    {
        return array_values($this->users);
    }

    public function findByEmail(string $email): ?User
    {
        $normalizedEmail = $this->normalizeEmail($email);

        foreach ($this->findAll() as $user) {
            if ($user->email === $normalizedEmail) {
                return $user;
            }
        }

        return null;
    }

    public function save(User $user): void
    {
        $id = $user->id;
        $normalizedEmail = $this->normalizeEmail($user->email);
        $normalizedFaculty = $this->normalizeFaculty($user->faculty);

        if ($id === null) {
            $this->db->query(
                'INSERT INTO users', [
                    'name' => $user->name,
                    'role' => $user->role,
                    'faculty' => $normalizedFaculty,
                    'active' => $user->active,
                    'password' => $user->passwordHash,
                    'email' => $normalizedEmail,
                    'admin' => $user->admin,
                ]
            );

            $newId = (int) $this->db->getInsertId();
            $this->users[$newId] = new User(
                name: $user->name,
                email: $normalizedEmail,
                passwordHash: $user->passwordHash,
                id: $newId,
                role: $user->role,
                faculty: $normalizedFaculty,
                active: $user->active,
                tags: $user->tags,
                attendedEventIds: $user->attendedEventIds,
                admin: $user->admin,
            );

            return;
        }

        $this->db->query(
            'UPDATE users SET name = ?, role = ?, faculty = ?, active = ?, password = ?, email = ?, admin = ? WHERE id = ?',
            $user->name,
            $user->role,
            $normalizedFaculty,
            $user->active,
            $user->passwordHash,
            $normalizedEmail,
            $user->admin,
            $id,
        );

        $this->users[$id] = new User(
            name: $user->name,
            email: $normalizedEmail,
            passwordHash: $user->passwordHash,
            id: $id,
            role: $user->role,
            faculty: $normalizedFaculty,
            active: $user->active,
            tags: $user->tags,
            attendedEventIds: $user->attendedEventIds,
            admin: $user->admin,
        );
    }

    public function findById(int $id): ?User
    {
        foreach ($this->findAll() as $user) {
            if ($user->id === $id) {
                return $user;
            }
        }

        return null;
    }

    public function deactivate(int $id): void
    {
        $user = $this->findById($id);
        if ($user === null) {
            return;
        }

        $this->save(new User(
            name: $user->name,
            email: $user->email,
            passwordHash: $user->passwordHash,
            id: $user->id,
            role: $user->role,
            faculty: $user->faculty,
            active: false,
            tags: $user->tags,
            attendedEventIds: $user->attendedEventIds,
            admin: $user->admin,
        ));
    }

    public function forgotPassword(string $email): bool
    {
        $user = $this->findByEmail($email);
        if ($user === null) {
            return false;
        }

        do {
            $resetCode = bin2hex(random_bytes(16));
            $row = $this->db->fetch('SELECT * FROM password_resets WHERE reset_code = ?', $resetCode);
        } while ($row !== null);

        $this->db->query(
            'INSERT INTO password_resets (`user_id`, `reset_code`) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                reset_code = VALUES(reset_code)', $user->id, $resetCode);

        // TODO: send /reset/<reset_code> via mail
        bdump("/reset/" . $resetCode);
        return true;
    }

    public function resetPassword(string $resetCode, string $password): bool
    {
        $row = $this->db->fetch('SELECT * FROM password_resets WHERE reset_code = ?', $resetCode);
        if ($row === null) {
            return false;
        }

        $newHash = $this->passwords->hash($password);
        $user = $this->findById($row->user_id);
        if ($user === null) return false;

        $this->save(new User(
            name: $user->name,
            email: $user->email,
            passwordHash: $newHash,
            id: $user->id,
            role: $user->role,
            faculty: $user->faculty,
            active: $user->active,
            tags: $user->tags,
            attendedEventIds: $user->attendedEventIds,
            admin: $user->admin,
        ));

        $this->db->query('DELETE FROM password_resets WHERE reset_code = ?', $resetCode);

        return true;
    }

    private function hydrateUser(array $data): User
    {
        return new User(
            name: $data['name'] ?? 'Neznámý',
            email: $data['email'] ?? '',
            passwordHash: $data['passwordHash'] ?? '',
            id: isset($data['id']) ? (int) $data['id'] : null,
            role: $data['role'] ?? 'student',
            faculty: $this->normalizeFaculty($data['faculty'] ?? null),
            active: (bool) ($data['active'] ?? true),
            tags: $data['tags'] ?? [],
            attendedEventIds: array_map('intval', $data['attendedEventIds'] ?? []),
            admin: (bool) ($data['admin'] ?? false),
        );
    }

    private function normalizeFaculty(mixed $faculty): ?string
    {
        $faculty = trim((string) $faculty);

        return $faculty !== '' ? $faculty : null;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
