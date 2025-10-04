@extends('layouts.app')

@section('title', $title ?? 'Owner Dashboard')

@section('content')
    <section class="grid gap-6">
        <header>
            <h1 class="text-3xl font-semibold text-slate-900">Owner Dashboard</h1>
            <p class="mt-2 text-sm text-slate-600">Monitor outlet performance, subscription status, and daily
                operations at a glance.</p>
        </header>
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-medium text-slate-500">Active Outlets</h2>
                <p class="mt-3 text-3xl font-semibold text-indigo-600">0</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-medium text-slate-500">Today's Revenue</h2>
                <p class="mt-3 text-3xl font-semibold text-indigo-600">$0.00</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-medium text-slate-500">Pending Syncs</h2>
                <p class="mt-3 text-3xl font-semibold text-indigo-600">0</p>
            </div>
        </div>
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Next Steps</h2>
            <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-slate-600">
                <li>Connect your outlets and invite staff members.</li>
                <li>Configure subscription plans and feature access.</li>
                <li>Enable automatic stock sync for your inventory.</li>
            </ul>
        </section>
    </section>
@endsection
