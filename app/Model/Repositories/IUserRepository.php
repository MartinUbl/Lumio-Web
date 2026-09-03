<?php
declare(strict_types=1);

namespace App\Model\Repositories;

use App\Model\Entities\User;

interface IUserRepository
{
    /** @return User[] */
    public function findAll(): array;

    public function findById(int $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;

    public function deactivate(int $id): void;
}
