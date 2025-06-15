<?php

namespace App\Repositories;

use App\Models\Puskesmas;
use App\Exceptions\PuskesmasNotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PuskesmasRepository implements PuskesmasRepositoryInterface
{
    /**
     * Find Puskesmas by ID with validation
     *
     * @param int $id
     * @return Puskesmas
     * @throws PuskesmasNotFoundException
     */
    public function findOrFail(int $id): Puskesmas
    {
        $puskesmas = $this->find($id);

        if (!$puskesmas) {
            Log::warning('Puskesmas not found', [
                'puskesmas_id' => $id,
                'method' => __METHOD__
            ]);

            throw new PuskesmasNotFoundException($id, [
                'method' => __METHOD__,
                'timestamp' => now()->toISOString()
            ]);
        }

        return $puskesmas;
    }

    /**
     * Find Puskesmas by ID
     *
     * @param int $id
     * @return Puskesmas|null
     */
    public function find(int $id): ?Puskesmas
    {
        return Puskesmas::find($id);
    }

    /**
     * Get all active Puskesmas
     *
     * @return Collection
     */
    public function getAllActive(): Collection
    {
        return Puskesmas::where('is_active', true)->get();
    }

    /**
     * Check if Puskesmas exists
     *
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return Puskesmas::where('id', $id)->exists();
    }

    /**
     * Get Puskesmas with caching
     *
     * @param int $id
     * @param int $cacheTtl Cache TTL in seconds
     * @return Puskesmas|null
     */
    public function findWithCache(int $id, int $cacheTtl = 3600): ?Puskesmas
    {
        $cacheKey = "puskesmas.{$id}";

        return Cache::remember($cacheKey, $cacheTtl, function () use ($id) {
            return $this->find($id);
        });
    }

    /**
     * Get all puskesmas
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return Puskesmas::all();
    }

    /**
     * Get puskesmas with pagination
     *
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate(int $perPage = 15)
    {
        return Puskesmas::paginate($perPage);
    }

    /**
     * Search puskesmas by name
     *
     * @param string $name
     * @return Collection
     */
    public function searchByName(string $name): Collection
    {
        return Puskesmas::where('name', 'like', '%' . $name . '%')->get();
    }

    /**
     * Get puskesmas for select dropdown
     *
     * @return Collection
     */
    public function getForSelect(): Collection
    {
        return Cache::remember('puskesmas.select_options', 3600, function () {
            return Puskesmas::select('id', 'name')->orderBy('name')->get();
        });
    }
}
