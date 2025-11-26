<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureTenantHasActivePlan
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('landing.login')
                ->with('warning', 'Silakan login untuk mengakses dashboard.');
        }

        $tenant = $user->currentTenant();

        if (! $tenant) {
            return redirect()->route('landing.pricing')
                ->with('warning', 'Tenant tidak ditemukan. Silakan hubungi support.');
        }

        if (! $tenant->plan_id) {
            return redirect()->route('landing.pricing')
                ->with('warning', 'Pilih paket terlebih dahulu untuk memulai trial 30 hari.');
        }

        $activeSubscription = $tenant->activeSubscription();

        if (! $activeSubscription || $activeSubscription->hasExpired()) {
            return redirect()->route('landing.pricing')
                ->with('warning', 'Langganan Anda tidak aktif. Silakan lanjutkan pembayaran atau perpanjang trial.');
        }

        return $next($request);
    }
}

