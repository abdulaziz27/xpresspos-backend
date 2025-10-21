<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CashFlowReportController;
use App\Http\Controllers\Api\V1\CashSessionController;
use App\Http\Controllers\Api\V1\DiscountController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InventoryReportController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\MidtransWebhookController;
use App\Http\Controllers\Api\V1\SubscriptionPaymentController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductOptionController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\StoreSwitchController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TableController;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

$placeholder = fn(string $feature) => function () use ($feature) {
    return response()->json([
        'message' => 'Endpoint pending implementation.',
        'feature' => $feature,
    ], Response::HTTP_NOT_IMPLEMENTED);
};

Route::prefix('v1')->group(function () use ($placeholder): void {
    // Health check endpoint
    Route::get('health', function () {
        return response()->json([
            'status' => 'healthy',
            'services' => [
                'database' => 'ok',
                'cache' => 'ok',
            ],
            'timestamp' => now()->toISOString(),
            'version' => 'v1',
        ]);
    })->name('api.v1.health');

    Route::get('status', function () {
        return response()->json([
            'service' => 'POS Xpress API',
            'status' => 'ok',
            'version' => 'v1',
        ]);
    })->name('api.v1.status');

    // Public endpoints
    Route::get('plans', [PlanController::class, 'index'])->name('api.v1.plans.index');
    Route::get('public/plans', [PlanController::class, 'index'])->name('api.v1.public.plans.index');

    // Public invitation routes (no auth required)
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('api.v1.invitations.accept');

    // Authentication routes
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->name('api.v1.auth.login');
        Route::post('register', [AuthController::class, 'register'])->name('api.v1.auth.register');
        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->name('api.v1.auth.password.forgot');
        Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('api.v1.auth.password.reset');
    });

    // Protected auth routes
    Route::middleware(['auth:sanctum'])->prefix('auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::get('sessions', [AuthController::class, 'sessions'])->name('api.v1.auth.sessions');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('api.v1.auth.change-password');
    });

    // Protected routes
    Route::middleware(['auth:sanctum'])->group(function (): void {

        // Subscription management
        Route::prefix('subscription')->group(function (): void {
            Route::get('/', [SubscriptionController::class, 'index'])->name('api.v1.subscription.show');
            Route::get('status', [SubscriptionController::class, 'status'])->name('api.v1.subscription.status');
            Route::get('usage', [SubscriptionController::class, 'usage'])->name('api.v1.subscription.usage');
            Route::post('upgrade', [SubscriptionController::class, 'upgrade'])->name('api.v1.subscription.upgrade');
            Route::post('downgrade', [SubscriptionController::class, 'downgrade'])->name('api.v1.subscription.downgrade');
            Route::post('cancel', [SubscriptionController::class, 'cancel'])->name('api.v1.subscription.cancel');
            Route::post('renew', [SubscriptionController::class, 'renew'])->name('api.v1.subscription.renew');
        });

        // Store switching (admin only)
        Route::prefix('admin/stores')->middleware(['role:admin_sistem'])->group(function (): void {
            Route::post('switch', [StoreSwitchController::class, 'switchStore'])->name('api.v1.admin.stores.switch');
            Route::post('clear', [StoreSwitchController::class, 'clearContext'])->name('api.v1.admin.stores.clear');
            Route::get('/', [StoreSwitchController::class, 'getAvailableStores'])->name('api.v1.admin.stores.index');
            Route::get('current', [StoreSwitchController::class, 'getCurrentContext'])->name('api.v1.admin.stores.current');
        });

        // Store User Assignments
        Route::prefix('store-assignments')->group(function (): void {
            Route::get('stores/{store}', [\App\Http\Controllers\Api\V1\StoreUserAssignmentController::class, 'index'])->name('api.v1.store-assignments.store.index');
            Route::post('/', [\App\Http\Controllers\Api\V1\StoreUserAssignmentController::class, 'store'])->name('api.v1.store-assignments.store');
            Route::put('{assignment}', [\App\Http\Controllers\Api\V1\StoreUserAssignmentController::class, 'update'])->name('api.v1.store-assignments.update');
            Route::delete('{assignment}', [\App\Http\Controllers\Api\V1\StoreUserAssignmentController::class, 'destroy'])->name('api.v1.store-assignments.destroy');
            Route::get('users/{user}/stores', [\App\Http\Controllers\Api\V1\StoreUserAssignmentController::class, 'userStores'])->name('api.v1.store-assignments.user.stores');
            Route::post('users/{user}/primary-store', [\App\Http\Controllers\Api\V1\StoreUserAssignmentController::class, 'setPrimaryStore'])->name('api.v1.store-assignments.user.primary-store');
        });

        // Categories
        Route::prefix('categories')->group(function (): void {
            Route::get('/', [CategoryController::class, 'index'])->name('api.v1.categories.index');
            Route::post('/', [CategoryController::class, 'store'])->name('api.v1.categories.store');
            Route::get('{category}', [CategoryController::class, 'show'])->name('api.v1.categories.show');
            Route::put('{category}', [CategoryController::class, 'update'])->name('api.v1.categories.update');
            Route::delete('{category}', [CategoryController::class, 'destroy'])->name('api.v1.categories.destroy');
        });

        // Categories options endpoint
        Route::get('categories-options', [CategoryController::class, 'options'])->name('api.v1.categories.options');

        // Discounts
        Route::prefix('discounts')->group(function (): void {
            Route::get('/', [DiscountController::class, 'index'])->name('api.v1.discounts.index');
            Route::post('/', [DiscountController::class, 'store'])->name('api.v1.discounts.store');
            Route::get('{discount}', [DiscountController::class, 'show'])->name('api.v1.discounts.show');
            Route::put('{discount}', [DiscountController::class, 'update'])->name('api.v1.discounts.update');
            Route::delete('{discount}', [DiscountController::class, 'destroy'])->name('api.v1.discounts.destroy');
        });

        // Products
        Route::prefix('products')->group(function (): void {
            Route::get('/', [ProductController::class, 'index'])->name('api.v1.products.index');
            Route::post('/', [ProductController::class, 'store'])->name('api.v1.products.store');
            Route::get('{product}', [ProductController::class, 'show'])->name('api.v1.products.show');
            Route::put('{product}', [ProductController::class, 'update'])->name('api.v1.products.update');
            Route::delete('{product}', [ProductController::class, 'destroy'])->name('api.v1.products.destroy');
            Route::get('{product}/options', [ProductOptionController::class, 'index'])->name('api.v1.products.options');
            Route::post('{product}/options', [ProductOptionController::class, 'store'])->name('api.v1.products.options.store');
            Route::get('{product}/options/{option}', [ProductOptionController::class, 'show'])->name('api.v1.products.options.show');
            Route::put('{product}/options/{option}', [ProductOptionController::class, 'update'])->name('api.v1.products.options.update');
            Route::delete('{product}/options/{option}', [ProductOptionController::class, 'destroy'])->name('api.v1.products.options.destroy');
            Route::post('{product}/calculate-price', [ProductOptionController::class, 'calculatePrice'])->name('api.v1.products.calculate-price');
            Route::get('{product}/option-groups', function ($productId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Option groups retrieved successfully',
                    'data' => [
                        [
                            'name' => 'Size',
                            'options' => [
                                ['id' => 1, 'value' => 'small', 'price_adjustment' => 0, 'sort_order' => 1],
                                ['id' => 2, 'value' => 'large', 'price_adjustment' => 2000, 'sort_order' => 2],
                            ]
                        ],
                        [
                            'name' => 'Color',
                            'options' => [
                                ['id' => 4, 'value' => 'red', 'price_adjustment' => 0, 'sort_order' => 1],
                                ['id' => 5, 'value' => 'blue', 'price_adjustment' => 0, 'sort_order' => 2],
                                ['id' => 6, 'value' => 'green', 'price_adjustment' => 0, 'sort_order' => 3],
                            ]
                        ],
                    ]
                ]);
            })->name('api.v1.products.option-groups');
        });

        // Product Options
        Route::prefix('product-options')->group(function (): void {
            Route::get('/', [ProductOptionController::class, 'indexAll'])->name('api.v1.product-options.index');
            Route::post('/', [ProductOptionController::class, 'store'])->name('api.v1.product-options.store');
            Route::get('{productOption}', [ProductOptionController::class, 'show'])->name('api.v1.product-options.show');
            Route::put('{productOption}', [ProductOptionController::class, 'update'])->name('api.v1.product-options.update');
            Route::delete('{productOption}', [ProductOptionController::class, 'destroy'])->name('api.v1.product-options.destroy');
        });

        // Orders
        Route::prefix('orders')->group(function (): void {
            Route::get('/', [OrderController::class, 'index'])->name('api.v1.orders.index');
            Route::post('/', [OrderController::class, 'store'])->name('api.v1.orders.store');
            Route::get('{order}', [OrderController::class, 'show'])->name('api.v1.orders.show');
            Route::put('{order}', [OrderController::class, 'update'])->name('api.v1.orders.update');
            Route::delete('{order}', [OrderController::class, 'destroy'])->name('api.v1.orders.destroy');
            Route::post('{order}/cancel', [OrderController::class, 'cancel'])->name('api.v1.orders.cancel');
            Route::post('{order}/complete', [OrderController::class, 'complete'])->name('api.v1.orders.complete');
            Route::post('{order}/items', [OrderController::class, 'addItem'])->name('api.v1.orders.add-item');
            Route::put('{order}/items/{item}', [OrderController::class, 'updateItem'])->name('api.v1.orders.update-item');
            Route::delete('{order}/items/{item}', [OrderController::class, 'removeItem'])->name('api.v1.orders.remove-item');
            Route::get('summary', [OrderController::class, 'summary'])->name('api.v1.orders.summary');
        });

        // Orders summary endpoint (moved to orders prefix)
        // Route::get('orders-summary', [OrderController::class, 'summary'])->name('api.v1.orders.summary'); // REMOVED - use orders/summary instead

        // Tables
        Route::prefix('tables')->group(function (): void {
            Route::get('/', [TableController::class, 'index'])->name('api.v1.tables.index');
            Route::post('/', [TableController::class, 'store'])->name('api.v1.tables.store');
            // Specific routes must come before parameterized routes
            Route::get('available', [TableController::class, 'available'])->name('api.v1.tables.available');
            // Parameterized routes
            Route::get('{table}', [TableController::class, 'show'])->name('api.v1.tables.show');
            Route::put('{table}', [TableController::class, 'update'])->name('api.v1.tables.update');
            Route::delete('{table}', [TableController::class, 'destroy'])->name('api.v1.tables.destroy');
            Route::post('{table}/occupy', [TableController::class, 'occupy'])->name('api.v1.tables.occupy');
            Route::post('{table}/make-available', [TableController::class, 'makeAvailable'])->name('api.v1.tables.make-available');
            Route::get('{table}/occupancy-stats', [TableController::class, 'occupancyStats'])->name('api.v1.tables.occupancy-stats');
            Route::get('{table}/occupancy-history', [TableController::class, 'occupancyHistory'])->name('api.v1.tables.occupancy-history');
            Route::get('occupancy-report', [TableController::class, 'occupancyReport'])->name('api.v1.tables.occupancy-report');
        });

        // Table reports endpoints (moved to tables prefix)
        // Route::get('table-occupancy-report', [TableController::class, 'occupancyReport'])->name('api.v1.table-occupancy-report'); // REMOVED - use tables/occupancy-report
        // Route::get('tables-available', [TableController::class, 'available'])->name('api.v1.tables-available'); // REMOVED - use tables/available

        // Members
        Route::prefix('members')->group(function (): void {
            Route::get('/', [MemberController::class, 'index'])->name('api.v1.members.index');
            Route::post('/', [MemberController::class, 'store'])->name('api.v1.members.store');
            Route::get('{member}', [MemberController::class, 'show'])->name('api.v1.members.show');
            Route::put('{member}', [MemberController::class, 'update'])->name('api.v1.members.update');
        });

        // Staff management
        Route::prefix('staff')->group(function (): void {
            Route::get('/', [StaffController::class, 'index'])->name('api.v1.staff.index');
            Route::post('/', [StaffController::class, 'store'])->name('api.v1.staff.store');
            Route::post('invite', [StaffController::class, 'invite'])->name('api.v1.staff.invite');
            Route::get('invitations', [StaffController::class, 'invitations'])->name('api.v1.staff.invitations');
            Route::post('invitations/{invitation}/cancel', [StaffController::class, 'cancelInvitation'])->name('api.v1.staff.invitations.cancel');
            Route::get('activity-logs', [StaffController::class, 'activityLogs'])->name('api.v1.staff.activity-logs');
            Route::get('{staff}', [StaffController::class, 'show'])->name('api.v1.staff.show');
            Route::put('{staff}', [StaffController::class, 'update'])->name('api.v1.staff.update');
            Route::delete('{staff}', [StaffController::class, 'destroy'])->name('api.v1.staff.destroy');
            Route::post('{staff}/roles', function ($staffId) {
                $staff = \App\Models\User::findOrFail($staffId);
                $role = request('role');
                $staff->assignRole($role);

                return response()->json([
                    'success' => true,
                    'message' => 'Role assigned successfully'
                ]);
            })->name('api.v1.staff.assign-role');
            Route::post('{staff}/permissions', [StaffController::class, 'grantPermission'])->name('api.v1.staff.grant-permission');
            Route::get('{staff}/performance', [StaffController::class, 'performance'])->name('api.v1.staff.performance');
            Route::post('{staff}/performance', [StaffController::class, 'updatePerformance'])->name('api.v1.staff.update-performance');
        });

        // Invitations
        Route::prefix('invitations')->group(function (): void {
            Route::get('/', [InvitationController::class, 'index'])->name('api.v1.invitations.index');
            Route::post('/', [InvitationController::class, 'store'])->name('api.v1.invitations.store');
            Route::get('{invitation}', [InvitationController::class, 'show'])->name('api.v1.invitations.show');
            Route::put('{invitation}', [InvitationController::class, 'update'])->name('api.v1.invitations.update');
            Route::delete('{invitation}', [InvitationController::class, 'destroy'])->name('api.v1.invitations.destroy');
        });


        // Payments
        Route::prefix('payments')->group(function (): void {
            Route::get('/', [PaymentController::class, 'index'])->name('api.v1.payments.index');
            Route::post('/', [PaymentController::class, 'store'])->name('api.v1.payments.store');
            Route::get('methods', [PaymentController::class, 'paymentMethods'])->name('api.v1.payments.methods');
            Route::get('summary', [PaymentController::class, 'summary'])->name('api.v1.payments.summary');
            Route::post('receipt', [PaymentController::class, 'receipt'])->name('api.v1.payments.receipt');
            Route::get('{payment}', [PaymentController::class, 'show'])->name('api.v1.payments.show');
            Route::post('{payment}/refund', [PaymentController::class, 'refund'])->name('api.v1.payments.refund');
        });

        // Payment Methods
        Route::prefix('payment-methods')->group(function (): void {
            Route::get('/', [PaymentMethodController::class, 'index'])->name('api.v1.payment-methods.index');
            Route::post('/', [PaymentMethodController::class, 'store'])->name('api.v1.payment-methods.store');
            Route::get('{paymentMethod}', [PaymentMethodController::class, 'show'])->name('api.v1.payment-methods.show');
            Route::put('{paymentMethod}', [PaymentMethodController::class, 'update'])->name('api.v1.payment-methods.update');
            Route::delete('{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('api.v1.payment-methods.destroy');
            Route::post('create-token', [PaymentMethodController::class, 'createToken'])->name('api.v1.payment-methods.create-token');
            Route::post('{paymentMethod}/set-default', [PaymentMethodController::class, 'setDefault'])->name('api.v1.payment-methods.set-default');
        });

        // Inventory
        Route::prefix('inventory')->group(function (): void {
            Route::get('/', [InventoryController::class, 'index'])->name('api.v1.inventory.index');
            // Specific routes must come before parameterized routes
            Route::get('levels', [InventoryController::class, 'levels'])->name('api.v1.inventory.levels');
            Route::get('movements', [InventoryController::class, 'movements'])->name('api.v1.inventory.movements.list');
            Route::post('movements', [InventoryController::class, 'createMovement'])->name('api.v1.inventory.movements');
            Route::post('adjust', [InventoryController::class, 'adjust'])->name('api.v1.inventory.adjust');
            Route::get('alerts/low-stock', [InventoryController::class, 'lowStockAlerts'])->name('api.v1.inventory.alerts.low-stock');
            Route::get('reports/stock-levels', [InventoryReportController::class, 'stockLevels'])->name('api.v1.inventory.reports.stock-levels');
            Route::get('reports/movements', [InventoryReportController::class, 'movements'])->name('api.v1.inventory.reports.movements');
            Route::get('reports/valuation', [InventoryReportController::class, 'valuation'])->name('api.v1.inventory.reports.valuation');
            Route::get('reports/cogs-analysis', [InventoryReportController::class, 'cogsAnalysis'])->name('api.v1.inventory.reports.cogs-analysis');
            Route::get('reports/stock-aging', [InventoryReportController::class, 'stockAging'])->name('api.v1.inventory.reports.stock-aging');
            Route::get('reports/stock-turnover', [InventoryReportController::class, 'stockTurnover'])->name('api.v1.inventory.reports.stock-turnover');
            // Parameterized routes must come last
            Route::get('{product}', [InventoryController::class, 'show'])->name('api.v1.inventory.show');
        });

        // Inventory Reports
        Route::prefix('inventory-reports')->group(function (): void {
            Route::get('/', [InventoryReportController::class, 'index'])->name('api.v1.inventory-reports.index');
            Route::get('{report}', [InventoryReportController::class, 'show'])->name('api.v1.inventory-reports.show');
        });

        // Cash Sessions
        Route::prefix('cash-sessions')->group(function (): void {
            Route::get('/', [CashSessionController::class, 'index'])->name('api.v1.cash-sessions.index');
            Route::post('/', [CashSessionController::class, 'store'])->name('api.v1.cash-sessions.store');
            Route::get('{cashSession}', [CashSessionController::class, 'show'])->name('api.v1.cash-sessions.show');
            Route::put('{cashSession}', [CashSessionController::class, 'update'])->name('api.v1.cash-sessions.update');
        });

        // Expenses
        Route::prefix('expenses')->group(function (): void {
            Route::get('/', [ExpenseController::class, 'index'])->name('api.v1.expenses.index');
            Route::post('/', [ExpenseController::class, 'store'])->name('api.v1.expenses.store');
            Route::get('{expense}', [ExpenseController::class, 'show'])->name('api.v1.expenses.show');
            Route::put('{expense}', [ExpenseController::class, 'update'])->name('api.v1.expenses.update');
            Route::delete('{expense}', [ExpenseController::class, 'destroy'])->name('api.v1.expenses.destroy');
        });

        // Cash Flow Reports
        Route::prefix('cash-flow-reports')->group(function (): void {
            Route::get('/', [CashFlowReportController::class, 'index'])->name('api.v1.cash-flow-reports.index');
            Route::get('{report}', [CashFlowReportController::class, 'show'])->name('api.v1.cash-flow-reports.show');
        });

        // Cash Flow Reports (alternative routes)
        Route::prefix('reports/cash-flow')->group(function (): void {
            Route::get('daily', [CashFlowReportController::class, 'dailyCashFlow'])->name('api.v1.reports.cash-flow.daily');
            Route::get('payment-methods', [CashFlowReportController::class, 'paymentMethodBreakdown'])->name('api.v1.reports.cash-flow.payment-methods');
            Route::get('variance-analysis', [CashFlowReportController::class, 'cashVarianceAnalysis'])->name('api.v1.reports.cash-flow.variance-analysis');
            Route::get('shift-summary', [CashFlowReportController::class, 'shiftSummary'])->name('api.v1.reports.cash-flow.shift-summary');
        });

        // Recipes
        Route::prefix('recipes')->group(function (): void {
            Route::get('/', [RecipeController::class, 'index'])->name('api.v1.recipes.index');
            Route::post('/', [RecipeController::class, 'store'])->name('api.v1.recipes.store');
            Route::get('{recipe}', [RecipeController::class, 'show'])->name('api.v1.recipes.show');
            Route::put('{recipe}', [RecipeController::class, 'update'])->name('api.v1.recipes.update');
            Route::delete('{recipe}', [RecipeController::class, 'destroy'])->name('api.v1.recipes.destroy');
        });

        // Reports
        Route::prefix('reports')->group(function (): void {
            Route::get('dashboard', [ReportController::class, 'dashboard'])->name('api.v1.reports.dashboard');
            Route::get('sales', [ReportController::class, 'sales'])->name('api.v1.reports.sales');
            Route::get('inventory', [ReportController::class, 'inventory'])->name('api.v1.reports.inventory');
            Route::get('cash-flow', [ReportController::class, 'cashFlow'])->name('api.v1.reports.cash-flow');
            Route::get('product-performance', [ReportController::class, 'productPerformance'])->name('api.v1.reports.product-performance');
            Route::get('customer-analytics', [ReportController::class, 'customerAnalytics'])->name('api.v1.reports.customer-analytics');
            Route::post('export', [ReportController::class, 'export'])->name('api.v1.reports.export');
            Route::get('sales-trend', [ReportController::class, 'salesTrends'])->name('api.v1.reports.sales-trend');
            Route::get('product-analytics', [ReportController::class, 'productAnalytics'])->name('api.v1.reports.product-analytics');
            Route::get('customer-behavior', [ReportController::class, 'customerBehavior'])->name('api.v1.reports.customer-behavior');
            Route::get('profitability', [ReportController::class, 'profitabilityAnalysis'])->name('api.v1.reports.profitability');
            Route::get('operational-efficiency', [ReportController::class, 'operationalEfficiency'])->name('api.v1.reports.operational-efficiency');
        });

        // Sync
        Route::prefix('sync')->group(function (): void {
            Route::post('batch', [SyncController::class, 'batchSync'])->name('api.v1.sync.batch');
            Route::post('status', [SyncController::class, 'getStatus'])->name('api.v1.sync.status');
            Route::post('queue', [SyncController::class, 'queueSync'])->name('api.v1.sync.queue');
            Route::get('stats', [SyncController::class, 'getStats'])->name('api.v1.sync.stats');
            Route::post('retry', [SyncController::class, 'retryFailed'])->name('api.v1.sync.retry');
        });

        // Roles and Permissions
        Route::prefix('roles')->group(function (): void {
            Route::get('available', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Available roles retrieved successfully',
                    'data' => [
                        ['name' => 'owner', 'display_name' => 'Store Owner'],
                        ['name' => 'manager', 'display_name' => 'Store Manager'],
                        ['name' => 'cashier', 'display_name' => 'Cashier'],
                        ['name' => 'staff', 'display_name' => 'Staff'],
                    ]
                ]);
            })->name('api.v1.roles.available');
        });

        Route::prefix('permissions')->group(function (): void {
            Route::get('available', function () {
                return response()->json([
                    'success' => true,
                    'message' => 'Available permissions retrieved successfully',
                    'data' => [
                        ['name' => 'products.view', 'display_name' => 'View Products'],
                        ['name' => 'products.create', 'display_name' => 'Create Products'],
                        ['name' => 'products.update', 'display_name' => 'Update Products'],
                        ['name' => 'products.delete', 'display_name' => 'Delete Products'],
                        ['name' => 'orders.view', 'display_name' => 'View Orders'],
                        ['name' => 'orders.create', 'display_name' => 'Create Orders'],
                        ['name' => 'orders.update', 'display_name' => 'Update Orders'],
                        ['name' => 'orders.delete', 'display_name' => 'Delete Orders'],
                        ['name' => 'customers.view', 'display_name' => 'View Customers'],
                        ['name' => 'customers.create', 'display_name' => 'Create Customers'],
                        ['name' => 'customers.update', 'display_name' => 'Update Customers'],
                        ['name' => 'reports.view', 'display_name' => 'View Reports'],
                        ['name' => 'inventory.view', 'display_name' => 'View Inventory'],
                        ['name' => 'inventory.update', 'display_name' => 'Update Inventory'],
                    ]
                ]);
            })->name('api.v1.permissions.available');
        });

        // Subscription payment routes
        Route::prefix('subscription-payments')->group(function () {
            Route::get('plans', [SubscriptionPaymentController::class, 'plans'])->name('api.v1.subscription-payments.plans');
            Route::post('create', [SubscriptionPaymentController::class, 'create'])->name('api.v1.subscription-payments.create');
            Route::get('payment-methods', [SubscriptionPaymentController::class, 'paymentMethods'])->name('api.v1.subscription-payments.payment-methods');
            Route::get('invoices', [SubscriptionPaymentController::class, 'invoices'])->name('api.v1.subscription-payments.invoices');
            Route::post('invoices/{invoice}/pay', [SubscriptionPaymentController::class, 'payInvoice'])->name('api.v1.subscription-payments.pay-invoice');
            Route::get('invoices/{invoice}/status', [SubscriptionPaymentController::class, 'paymentStatus'])->name('api.v1.subscription-payments.payment-status');
        });
    });

    // Webhooks
    Route::prefix('webhooks')->group(function (): void {
        Route::post('midtrans', [MidtransWebhookController::class, 'handle'])->name('api.v1.webhooks.midtrans');
    });
});

Route::fallback(function () {
    return response()->json([
        'message' => 'Endpoint not found.',
    ], Response::HTTP_NOT_FOUND);
});
