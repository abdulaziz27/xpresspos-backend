@extends('layouts.app')

@section('title', $title ?? 'Owner Dashboard')

@section('content')
    <section class="grid gap-6">
        <header>
            <h1 class="text-3xl font-semibold text-slate-900">Owner Dashboard</h1>
            <p class="mt-2 text-sm text-slate-600">Monitor outlet performance, subscription status, and daily
                operations at a glance.</p>
        </header>
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-medium text-slate-500">Today's Revenue</h2>
                <p class="mt-3 text-3xl font-semibold text-indigo-600">Rp {{ number_format($summary['revenue']['today'], 0, ',', '.') }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-medium text-slate-500">Today's Orders</h2>
                <p class="mt-3 text-3xl font-semibold text-indigo-600">{{ $summary['orders']['today'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-medium text-slate-500">Active Members</h2>
                <p class="mt-3 text-3xl font-semibold text-indigo-600">{{ $summary['customers']['active_members'] }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-medium text-slate-500">Low Stock Items</h2>
                <p class="mt-3 text-3xl font-semibold text-orange-600">{{ $summary['inventory']['low_stock'] }}</p>
            </div>
        </div>
        <div class="grid gap-6 md:grid-cols-2">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Revenue Summary</h2>
                <div class="mt-4 space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-600">Total Revenue</span>
                        <span class="font-medium">Rp {{ number_format($summary['revenue']['total'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-600">This Month</span>
                        <span class="font-medium">Rp {{ number_format($summary['revenue']['month'], 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-600">Average Order Value</span>
                        <span class="font-medium">Rp {{ number_format($summary['orders']['average_value'], 0, ',', '.') }}</span>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Orders Summary</h2>
                <div class="mt-4 space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-600">Total Orders</span>
                        <span class="font-medium">{{ number_format($summary['orders']['total']) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-600">This Month</span>
                        <span class="font-medium">{{ number_format($summary['orders']['month']) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-600">Today</span>
                        <span class="font-medium">{{ number_format($summary['orders']['today']) }}</span>
                    </div>
                </div>
            </section>
        </div>

        @if($summary['top_products']->isNotEmpty())
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Top Products (Last 30 Days)</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Quantity Sold</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($summary['top_products'] as $product)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $product['product_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ number_format($product['total_quantity']) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">Rp {{ number_format($product['total_revenue'], 0, ',', '.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        @if($summary['recent_payments']->isNotEmpty())
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Recent Payments</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Payment ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Method</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($summary['recent_payments'] as $payment)
                        <tr>
                            <td class="px-4 py-3 text-sm text-slate-900">{{ $payment['id'] }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $payment['payment_method'] }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">Rp {{ number_format($payment['amount'], 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $payment['processed_at'] ? \Carbon\Carbon::parse($payment['processed_at'])->format('d M Y H:i') : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif
    </section>
@endsection
