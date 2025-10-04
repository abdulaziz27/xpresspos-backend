<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLandingSubscription;
use App\Models\LandingSubscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:100'],
            'preferred_contact_method' => ['nullable', 'string', 'in:email,phone,whatsapp'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'plan' => ['nullable', 'string', 'max:100'],
        ]);

        $subscription = LandingSubscription::create([
            'email' => $validated['email'],
            'name' => $validated['name'] ?? null,
            'company' => $validated['company'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'country' => $validated['country'] ?? null,
            'preferred_contact_method' => $validated['preferred_contact_method'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'plan' => $validated['plan'] ?? null,
            'status' => 'pending',
            'stage' => 'new',
            'meta' => [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->headers->get('referer'),
            ],
        ]);

        ProcessLandingSubscription::dispatch($subscription->id);

        return back()->with('status', __('Thanks! We will reach out shortly.'));
    }
}
