<?php

namespace App\Services\Statistics;

use App\Models\Puskesmas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PuskesmasService
{
    /**
     * Get puskesmas data based on user role and request parameters
     * 
     * @param Request $request
     * @return array
     */
    public function getPuskesmasData(Request $request)
    {
        // Ambil data puskesmas
        $puskesmasQuery = Puskesmas::query();

        // Jika ada filter nama puskesmas (hanya untuk admin)
        if (Auth::user()->is_admin && $request->has('name')) {
            $puskesmasQuery->where('name', 'like', '%' . $request->name . '%');
        }

        // Jika user bukan admin, filter data ke puskesmas user
        if (!Auth::user()->is_admin) {
            $userPuskesmas = Auth::user()->puskesmas_id;
            if ($userPuskesmas) {
                $puskesmasQuery->where('id', $userPuskesmas);
            } else {
                // Log this issue to debug
                Log::warning('Puskesmas user without puskesmas_id: ' . Auth::user()->id);

                // Try to find a puskesmas with matching name as fallback
                $puskesmasWithSameName = Puskesmas::where('name', 'like', '%' . Auth::user()->name . '%')->first();

                if ($puskesmasWithSameName) {
                    $puskesmasQuery->where('id', $puskesmasWithSameName->id);

                    // Update the user with the correct puskesmas_id for future requests
                    Auth::user()->update(['puskesmas_id' => $puskesmasWithSameName->id]);

                    Log::info('Updated user ' . Auth::user()->id . ' with puskesmas_id ' . $puskesmasWithSameName->id);
                } else {
                    // Kembalikan data kosong dengan pesan
                    return [
                        'error' => 'User puskesmas tidak terkait dengan puskesmas manapun. Hubungi administrator.',
                        'puskesmas' => collect([])
                    ];
                }
            }
        }

        return [
            'puskesmas' => $puskesmasQuery->get()
        ];
    }
}