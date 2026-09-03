<?php
declare(strict_types=1);

namespace App\Model\Entities;

class Expert
{
    public function __construct(
        public string $degree,
        public string $name,
        public readonly ?int $id = null,
        public ?string $institution = null,
        public ?string $address = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $note = null,
        public array $tags = [],
        public bool $active = true,
    ) {}
}
