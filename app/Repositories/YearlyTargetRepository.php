<?php

namespace App\Repositories;

use App\Models\YearlyTarget;

class YearlyTargetRepository
{
    public function getByPuskesmasAndTypeAndYear($puskesmasId, $type, $year)
    {
        return YearlyTarget::where('puskesmas_id', $puskesmasId)
            ->where('disease_type', $type)
            ->where('year', $year)
            ->first();
    }

    public function getByYearAndType($year, $type)
    {
        return YearlyTarget::where('year', $year)
            ->where('disease_type', $type)
            ->get();
    }

    public function getByYearAndTypeAndIds($year, $type, $ids)
    {
        return YearlyTarget::where('year', $year)
            ->where('disease_type', $type)
            ->whereIn('puskesmas_id', $ids)
            ->get();
    }

    public function sumTargetByYearAndType($year, $type)
    {
        return YearlyTarget::where('year', $year)
            ->where('disease_type', $type)
            ->sum('target_count');
    }

    public function getAllByYear($year)
    {
        return YearlyTarget::where('year', $year)->get();
    }

    public function getTotalTargetCount($puskesmasIds, $type, $year)
    {
        return YearlyTarget::where('year', $year)
            ->where('disease_type', $type)
            ->whereIn('puskesmas_id', $puskesmasIds)
            ->sum('target_count');
    }
}
