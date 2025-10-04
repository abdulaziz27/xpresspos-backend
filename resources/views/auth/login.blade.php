@extends('layouts.app')

@section('title', 'Login - POS Xpress')

@section('content')
    <section class="mx-auto max-w-md space-y-6">
        <header class="space-y-2 text-center">
            <h1 class="text-2xl font-semibold text-slate-900">Sign in to POS Xpress</h1>
            <p class="text-sm text-slate-600">Authentication flow will be implemented with Sanctum & Spatie roles.</p>
        </header>
        <form method="POST" action="#" class="space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <div class="space-y-1">
                <label for="email" class="text-sm font-medium text-slate-700">Email</label>
                <input id="email" name="email" type="email" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="owner@example.com" disabled>
            </div>
            <div class="space-y-1">
                <label for="password" class="text-sm font-medium text-slate-700">Password</label>
                <input id="password" name="password" type="password" class="w-full rounded border border-slate-300 px-3 py-2" placeholder="********" disabled>
            </div>
            <button type="button" class="w-full rounded bg-indigo-500 px-3 py-2 text-white" disabled>Coming Soon</button>
        </form>
    </section>
@endsection
