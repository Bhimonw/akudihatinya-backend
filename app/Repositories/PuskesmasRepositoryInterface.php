<?php

namespace App\Repositories;

use App\Models\Puskesmas;
use Illuminate\Database\Eloquent\Collection;

interface PuskesmasRepositoryInterface
{
    /**
     * Find Puskesmas by ID with validation
     *
     * @param int $id
     * @return Puskesmas
     * @throws \App\Exceptions\PuskesmasNotFoundException
     */
    public function findOrFail(int $id): Puskesmas;

    /**
     * Find Puskesmas by ID
     *
     * @param int $id
     * @return Puskesmas|null
     */
    public function find(int $id): ?Puskesmas;

    /**
     * Get all active Puskesmas
     *
     * @return Collection
     */
    public function getAllActive(): Collection;

    /**
     * Check if Puskesmas exists
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool;

    /**
     * Get Puskesmas with caching
     *
     * @param int $id
     * @param int $cacheTtl Cache TTL in seconds (default: 3600)
     * @return Puskesmas|null
     */
    public function findWithCache(int $id, int $cacheTtl = 3600): ?Puskesmas;

    /**
     * Get Puskesmas list for dropdown/select
     *
     * @return Collection
     */
    public function getForSelect(): Collection;

    /**
     * Search Puskesmas by name
     *
     * @param string $name
     * @return Collection
     */
    public function searchByName(string $name): Collection;

    /**
     * Get filtered puskesmas based on user role and request parameters
     *
     * @param \Illuminate\Http\Request|null $request
     * @return Collection
     */
    public function getFilteredPuskesmas($request = null): Collection;

    /**
     * Get filtered puskesmas query based on user role and request parameters
     *
     * @param \Illuminate\Http\Request|null $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getFilteredPuskesmasQuery($request = null);

    /**
     * Get all puskesmas IDs
     *
     * @return array
     */
    public function getAllPuskesmasIds(): array;

    /**
     * Get total count of puskesmas
     *
     * @return int
     */
    public function getTotalCount(): int;

    /**
     * Get all puskesmas with selected columns
     *
     * @param array $columns
     * @return Collection
     */
    public function getAllPuskesmas(array $columns = ['*']): Collection;
}
