<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class EloquentRepository implements Repository
{
    public function __construct(protected readonly Model $model) {}

    /**
     * @return Collection<int, Model>
     */
    public function all(): Collection
    {
        return $this->model->newQuery()->get();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->newQuery()->paginate($perPage);
    }

    public function find(int|string $id): ?Model
    {
        return $this->model->newQuery()->find($id);
    }
}
