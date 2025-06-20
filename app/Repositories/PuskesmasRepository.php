<?php

namespace App\Repositories;

use App\Models\Puskesmas;
use App\Exceptions\PuskesmasNotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

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

    /**
     * Get filtered puskesmas based on user role and request parameters
     *
     * @param \Illuminate\Http\Request|null $request
     * @return Collection
     */
    public function getFilteredPuskesmas($request = null): Collection
    {
        return $this->getFilteredPuskesmasQuery($request)->get();
    }

    /**
     * Get filtered puskesmas query based on user role and request parameters
     *
     * @param \Illuminate\Http\Request|null $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getFilteredPuskesmasQuery($request = null)
    {
        $puskesmasQuery = Puskesmas::query();

        // Filter berdasarkan role
        if (!Auth::user()->isAdmin()) {
            $userPuskesmas = Auth::user()->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                // Try to find a puskesmas with matching name as fallback
                $puskesmasWithSameName = Puskesmas::where('name', 'like', '%' . Auth::user()->name . '%')->first();

                if ($puskesmasWithSameName) {
                    $puskesmasQuery->where('id', $puskesmasWithSameName->id);
                    // Update the user with the correct puskesmas_id for future requests
                    Auth::user()->update(['puskesmas_id' => $puskesmasWithSameName->id]);
                    Log::info('Updated user ' . Auth::user()->id . ' with puskesmas_id ' . $puskesmasWithSameName->id);
                } else {
                    // Return empty query
                    $puskesmasQuery->whereRaw('1 = 0'); // Force empty result
                }
            }
        } else {
            // Admin dapat filter berdasarkan nama puskesmas
            if ($request && $request->has('name')) {
                $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
            }
        }

        return $puskesmasQuery;
    }

    /**
     * Get all puskesmas IDs
     *
     * @return array
     */
    public function getAllPuskesmasIds(): array
    {
        return Puskesmas::pluck('id')->toArray();
    }

    /**
     * Get total count of puskesmas
     *
     * @return int
     */
    public function getTotalCount(): int
    {
        return Puskesmas::count();
    }

    /**
     * Get all puskesmas with selected columns
     *
     * @param array $columns
     * @return Collection
     */
    public function getAllPuskesmas(array $columns = ['*']): Collection
    {
        return Puskesmas::select($columns)->get();
    }
}
