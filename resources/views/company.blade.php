@extends('layouts.app')

@section('title', 'Company Profile - POS Xpress')

@section('content')
    <section class="space-y-6">
        <h1 class="text-3xl font-semibold text-slate-900">Company Profile</h1>
        <p class="text-slate-600">
            POS Xpress is a technology company focused on empowering retailers with modern, cloud-native POS
            infrastructure. Our platform is built to scale across multiple outlets, integrate seamlessly with
            third-party services, and deliver actionable insights for operators.
        </p>
        <div class="grid gap-4 md:grid-cols-2">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Mission</h2>
                <p class="text-sm text-slate-600">
                    Help retailers operate smarter through reliable, real-time data and automation that works both online
                    and offline.
                </p>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Vision</h2>
                <p class="text-sm text-slate-600">
                    Become the trusted operating system for growing retail brands across Southeast Asia.
                </p>
            </article>
        </div>
    </section>
@endsection
