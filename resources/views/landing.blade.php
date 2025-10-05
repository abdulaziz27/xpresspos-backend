@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="grid gap-8 md:grid-cols-2">
        <div class="space-y-6">
            <h1 class="text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">POS Xpress Backend</h1>
            <p class="text-lg text-slate-600">
                Modern backend platform for high-growth retailers. Designed for multi-outlet operations, advanced
                inventory tracking, subscription management, and offline-first POS experiences.
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="http://{{ config('domains.owner') }}" class="rounded bg-indigo-600 px-4 py-2 text-white shadow hover:bg-indigo-500">Explore Owner Dashboard</a>
                <a href="/admin" class="rounded border border-indigo-600 px-4 py-2 text-indigo-600 hover:bg-indigo-50">Admin Panel</a>
                <a href="{{ url('/docs') }}" class="rounded border border-slate-300 px-4 py-2 text-slate-600 hover:bg-slate-100">API Docs</a>
            </div>
        </div>
        <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Platform Highlights</h2>
            <ul class="space-y-2 text-sm text-slate-600">
                <li>Key features are fully multi-tenant aware.</li>
                <li>Subscription tiers map to plan features.</li>
                <li>Inventory and COGS managed in real time.</li>
                <li>Filament-powered admin panel.</li>
                <li>Offline-first sync services.</li>
            </ul>
        </div>
    </section>
@endsection
