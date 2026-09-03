<?php
declare(strict_types=1);

namespace App\Model\Entities;

class User
{
    public function __construct(
        public string $name,
        public string $email,
        public string $passwordHash,
        public readonly ?int $id = null,
        public string $role = 'student',
        public ?string $faculty = null,
        public bool $active = true,
        /** @var string[] */
        public array $tags = [],
        /** @var int[] */
        public array $attendedEventIds = [],
        public bool $admin = false,
    ) {}
}
