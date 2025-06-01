<?php

namespace App\Repositories;

use App\Models\Puskesmas;

class PuskesmasRepository
{
    public function all()
    {
        return Puskesmas::all();
    }

    public function findById($id)
    {
        return Puskesmas::find($id);
    }

    public function findByName($name)
    {
        return Puskesmas::where('name', 'like', "%$name%")->get();
    }

    public function filterByUser($user)
    {
        if ($user->is_admin) {
            return Puskesmas::query();
        }
        return Puskesmas::where('id', $user->puskesmas_id);
    }

    public function paginate($perPage)
    {
        return Puskesmas::paginate($perPage);
    }

    public function getByIds($ids)
    {
        return Puskesmas::whereIn('id', $ids)->get();
    }
}
