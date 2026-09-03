<?php
declare(strict_types=1);

namespace App\Model\Repositories;

use App\Model\Entities\Event;

interface IEventRepository
{
    /** @return Event[] */
    public function findAll(): array;

    /** @return Event[] */
    public function findApproved(): array;

    public function findById(int $id): ?Event;

    public function save(Event $event): Event;

    public function hide(int $id): void;
}