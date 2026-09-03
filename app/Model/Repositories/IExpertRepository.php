<?php
declare(strict_types=1);

namespace App\Model\Repositories;

use App\Model\Entities\Expert;

interface IExpertRepository
{
    /** @return Expert[] */
    public function findAll(bool $activeOnly = false): array;

    public function findById(int $id, bool $activeOnly = false): ?Expert;

    public function save(Expert $expert): Expert;

    public function hide(int $id): void;

    public function show(int $id): void;
}
