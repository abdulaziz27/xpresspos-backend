<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Store;
use App\Exports\DailyCashReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CashFlowReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'tenant.scope']);
        // Remove strict permission middleware for MVP testing
        // Owner role should have access to all report operations
    }

    /**
     * Get daily cash flow report.
     */
    public function dailyCashFlow(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->start_date ?? $request->date ?? now()->toDateString();
        $endDate = $request->end_date ?? $request->date ?? now()->toDateString();

        // Get cash sessions for the period
        $cashSessions = CashSession::whereBetween('opened_at', [$startDate, $endDate . ' 23:59:59'])
            ->with(['user:id,name,email'])
            ->get();

        // Get payments by method for the period
        $paymentsByMethod = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get();

        // Get expenses for the period
        $expenses = Expense::whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->select('category', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get();

        $report = [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'cash_sessions' => [
                'total_sessions' => $cashSessions->count(),
                'open_sessions' => $cashSessions->where('status', 'open')->count(),
                'closed_sessions' => $cashSessions->where('status', 'closed')->count(),
                'total_opening_balance' => $cashSessions->sum('opening_balance'),
                'total_closing_balance' => $cashSessions->where('status', 'closed')->sum('closing_balance'),
                'total_cash_sales' => $cashSessions->where('status', 'closed')->sum('cash_sales'),
                'total_cash_expenses' => $cashSessions->where('status', 'closed')->sum('cash_expenses'),
                'total_variance' => $cashSessions->where('status', 'closed')->sum('variance'),
                'sessions_with_variance' => $cashSessions->where('status', 'closed')
                    ->filter(function ($session) {
                        return abs($session->variance) > 0.01;
                    })->count(),
                'sessions' => $cashSessions,
            ],
            'payments_by_method' => $paymentsByMethod,
            'expenses_by_category' => $expenses,
            'summary' => [
                'total_revenue' => $paymentsByMethod->sum('total'),
                'total_expenses' => $expenses->sum('total'),
                'net_cash_flow' => $paymentsByMethod->sum('total') - $expenses->sum('total'),
                'cash_revenue' => $paymentsByMethod->where('payment_method', 'cash')->first()->total ?? 0,
                'non_cash_revenue' => $paymentsByMethod->whereNotIn('payment_method', ['cash'])->sum('total'),
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $report,
            'message' => 'Daily cash flow report generated successfully'
        ]);
    }

    /**
     * Export daily cash flow report to Excel.
     */
    public function exportDailyCashFlow(Request $request)
    {
        $request->validate([
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'store_id' => 'nullable|uuid|exists:stores,id',
            'tenant_id' => 'nullable|uuid|exists:tenants,id',
        ]);

        $startDate = $request->start_date ?? $request->date ?? now()->toDateString();
        $endDate = $request->end_date ?? $request->date ?? now()->toDateString();
        $storeId = $request->store_id;
        $tenantId = $request->tenant_id ?? auth()->user()?->currentTenant()?->id;

        // Get stores to export
        if ($storeId) {
            // Export for specific store
            $stores = Store::where('id', $storeId)->get();
        } else {
            // Export for all stores in tenant
            $stores = Store::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();
        }

        if ($stores->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada store yang ditemukan untuk di-export'
            ], 404);
        }

        // Generate reports for all stores
        $reports = [];
        foreach ($stores as $store) {
            $reports[] = [
                'store' => $store,
                'data' => $this->generateStoreReport($store, $startDate, $endDate)
            ];
        }
        
        // Generate filename
        $startDateFormatted = Carbon::parse($startDate)->format('Y-m-d');
        $endDateFormatted = Carbon::parse($endDate)->format('Y-m-d');
        $filename = "laporan_kas_harian_{$startDateFormatted}_to_{$endDateFormatted}_" . now()->format('Y-m-d_H-i-s') . '.xlsx';

        return Excel::download(new DailyCashReportExport($reports), $filename);
    }

    /**
     * Generate report data for a specific store.
     */
    private function generateStoreReport(Store $store, string $startDate, string $endDate): array
    {
        // Get cash sessions for the store and period
        $cashSessions = CashSession::where('store_id', $store->id)
            ->whereBetween('opened_at', [$startDate, $endDate . ' 23:59:59'])
            ->with(['user:id,name,email'])
            ->get();

        // Get payments by method for the store and period
        $paymentsByMethod = Payment::where('store_id', $store->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get();

        // Get expenses for the store and period
        $expenses = Expense::where('store_id', $store->id)
            ->whereDate('expense_date', '>=', $startDate)
            ->whereDate('expense_date', '<=', $endDate)
            ->select('category', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->get();

        return [
            'store_name' => $store->name,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'cash_sessions' => [
                'total_sessions' => $cashSessions->count(),
                'open_sessions' => $cashSessions->where('status', 'open')->count(),
                'closed_sessions' => $cashSessions->where('status', 'closed')->count(),
                'total_opening_balance' => $cashSessions->sum('opening_balance'),
                'total_closing_balance' => $cashSessions->where('status', 'closed')->sum('closing_balance'),
                'total_cash_sales' => $cashSessions->where('status', 'closed')->sum('cash_sales'),
                'total_cash_expenses' => $cashSessions->where('status', 'closed')->sum('cash_expenses'),
                'total_variance' => $cashSessions->where('status', 'closed')->sum('variance'),
                'sessions_with_variance' => $cashSessions->where('status', 'closed')
                    ->filter(function ($session) {
                        return abs($session->variance) > 0.01;
                    })->count(),
                'sessions' => $cashSessions->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'opened_at' => $session->opened_at,
                        'closed_at' => $session->closed_at,
                        'user' => $session->user,
                        'status' => $session->status,
                        'opening_balance' => $session->opening_balance,
                        'closing_balance' => $session->closing_balance,
                        'expected_balance' => $session->expected_balance,
                        'cash_sales' => $session->cash_sales,
                        'cash_expenses' => $session->cash_expenses,
                        'variance' => $session->variance,
                    ];
                })->toArray(),
            ],
            'payments_by_method' => $paymentsByMethod->map(function ($payment) {
                return [
                    'payment_method' => $payment->payment_method,
                    'count' => $payment->count,
                    'total' => $payment->total,
                ];
            })->toArray(),
            'expenses_by_category' => $expenses->map(function ($expense) {
                return [
                    'category' => $expense->category,
                    'count' => $expense->count,
                    'total' => $expense->total,
                ];
            })->toArray(),
            'summary' => [
                'total_revenue' => $paymentsByMethod->sum('total'),
                'total_expenses' => $expenses->sum('total'),
                'net_cash_flow' => $paymentsByMethod->sum('total') - $expenses->sum('total'),
                'cash_revenue' => $paymentsByMethod->where('payment_method', 'cash')->first()->total ?? 0,
                'non_cash_revenue' => $paymentsByMethod->whereNotIn('payment_method', ['cash'])->sum('total'),
            ]
        ];
    }

    /**
     * Get payment method breakdown report.
     */
    public function paymentMethodBreakdown(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        $startDate = $request->start_date ?? now()->subDays(30)->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();
        $groupBy = $request->group_by ?? 'day';

        // Use MySQL-compatible date formatting
        $dateFormat = match ($groupBy) {
            'week' => "DATE_FORMAT(created_at, '%Y-%u')",
            'month' => "DATE_FORMAT(created_at, '%Y-%m')",
            default => "DATE_FORMAT(created_at, '%Y-%m-%d')",
        };

        $payments = Payment::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->select(
                'payment_method',
                DB::raw("$dateFormat as period"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('payment_method', 'period')
            ->orderBy('period')
            ->get();

        $breakdown = $payments->groupBy('period')->map(function ($periodPayments, $period) {
            return [
                'period' => $period,
                'methods' => $periodPayments->groupBy('payment_method')->map(function ($methodPayments, $method) {
                    return [
                        'method' => $method,
                        'count' => $methodPayments->sum('count'),
                        'total' => $methodPayments->sum('total'),
                    ];
                })->values(),
                'total_count' => $periodPayments->sum('count'),
                'total_amount' => $periodPayments->sum('total'),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'group_by' => $groupBy,
                ],
                'breakdown' => $breakdown,
                'summary' => [
                    'total_transactions' => $payments->sum('count'),
                    'total_amount' => $payments->sum('total'),
                    'methods_summary' => $payments->groupBy('payment_method')->map(function ($methodPayments, $method) {
                        return [
                            'method' => $method,
                            'count' => $methodPayments->sum('count'),
                            'total' => $methodPayments->sum('total'),
                            'percentage' => 0, // Will be calculated on frontend
                        ];
                    })->values(),
                ]
            ],
            'message' => 'Payment method breakdown report generated successfully'
        ]);
    }

    /**
     * Get cash variance analysis report.
     */
    public function cashVarianceAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $startDate = $request->start_date ?? now()->subDays(30)->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $query = CashSession::where('status', 'closed')
            ->whereBetween('opened_at', [$startDate, $endDate . ' 23:59:59'])
            ->with(['user:id,name,email']);

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $sessions = $query->get();

        $analysis = [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'total_sessions' => $sessions->count(),
                'sessions_with_variance' => $sessions->filter(function ($session) {
                    return abs($session->variance) > 0.01;
                })->count(),
                'total_variance' => $sessions->sum('variance'),
                'average_variance' => $sessions->avg('variance'),
                'positive_variance' => $sessions->where('variance', '>', 0.01)->sum('variance'),
                'negative_variance' => $sessions->where('variance', '<', -0.01)->sum('variance'),
            ],
            'variance_by_user' => $sessions->groupBy('user_id')->map(function ($userSessions, $userId) {
                $user = $userSessions->first()->user;
                return [
                    'user' => $user,
                    'sessions_count' => $userSessions->count(),
                    'sessions_with_variance' => $userSessions->filter(function ($session) {
                        return abs($session->variance) > 0.01;
                    })->count(),
                    'total_variance' => $userSessions->sum('variance'),
                    'average_variance' => $userSessions->avg('variance'),
                    'variance_rate' => $userSessions->filter(function ($session) {
                        return abs($session->variance) > 0.01;
                    })->count() / $userSessions->count() * 100,
                ];
            })->values(),
            'sessions_with_significant_variance' => $sessions->filter(function ($session) {
                return abs($session->variance) > 10; // Variance greater than $10
            })->map(function ($session) {
                return [
                    'id' => $session->id,
                    'user' => $session->user,
                    'opened_at' => $session->opened_at,
                    'closed_at' => $session->closed_at,
                    'opening_balance' => $session->opening_balance,
                    'closing_balance' => $session->closing_balance,
                    'expected_balance' => $session->expected_balance,
                    'variance' => $session->variance,
                    'cash_sales' => $session->cash_sales,
                    'cash_expenses' => $session->cash_expenses,
                ];
            })->values(),
        ];

        return response()->json([
            'success' => true,
            'data' => $analysis,
            'message' => 'Cash variance analysis report generated successfully'
        ]);
    }

    /**
     * Get shift-based financial summary.
     */
    public function shiftSummary(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $startDate = $request->start_date ?? now()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $query = CashSession::whereBetween('opened_at', [$startDate, $endDate . ' 23:59:59'])
            ->with(['user:id,name,email', 'expenses']);

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $sessions = $query->orderBy('opened_at', 'desc')->get();

        $summary = $sessions->map(function ($session) {
            // Get payments for this session period
            $sessionPayments = Payment::where('status', 'completed')
                ->whereBetween('created_at', [
                    $session->opened_at,
                    $session->closed_at ?? now()
                ])
                ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                ->groupBy('payment_method')
                ->get();

            return [
                'session' => [
                    'id' => $session->id,
                    'user' => $session->user,
                    'status' => $session->status,
                    'opened_at' => $session->opened_at,
                    'closed_at' => $session->closed_at,
                    'duration_hours' => $session->closed_at
                        ? $session->opened_at->diffInHours($session->closed_at)
                        : $session->opened_at->diffInHours(now()),
                ],
                'cash_flow' => [
                    'opening_balance' => $session->opening_balance,
                    'closing_balance' => $session->closing_balance,
                    'expected_balance' => $session->expected_balance,
                    'variance' => $session->variance,
                    'cash_sales' => $session->cash_sales,
                    'cash_expenses' => $session->cash_expenses,
                ],
                'payments_by_method' => $sessionPayments,
                'expenses' => [
                    'total_expenses' => $session->expenses->sum('amount'),
                    'expense_count' => $session->expenses->count(),
                    'expenses_by_category' => $session->expenses->groupBy('category')->map(function ($categoryExpenses, $category) {
                        return [
                            'category' => $category,
                            'count' => $categoryExpenses->count(),
                            'total' => $categoryExpenses->sum('amount'),
                        ];
                    })->values(),
                ],
                'performance' => [
                    'total_revenue' => $sessionPayments->sum('total'),
                    'net_cash_flow' => $sessionPayments->sum('total') - $session->expenses->sum('amount'),
                    'transactions_count' => $sessionPayments->sum('count'),
                    'average_transaction' => $sessionPayments->sum('count') > 0
                        ? $sessionPayments->sum('total') / $sessionPayments->sum('count')
                        : 0,
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'shifts' => $summary,
                'summary' => [
                    'total_shifts' => $sessions->count(),
                    'open_shifts' => $sessions->where('status', 'open')->count(),
                    'closed_shifts' => $sessions->where('status', 'closed')->count(),
                    'total_revenue' => $summary->sum('performance.total_revenue'),
                    'total_expenses' => $summary->sum('expenses.total_expenses'),
                    'net_cash_flow' => $summary->sum('performance.net_cash_flow'),
                    'total_variance' => $sessions->where('status', 'closed')->sum('variance'),
                ]
            ],
            'message' => 'Shift-based financial summary generated successfully'
        ]);
    }
}
