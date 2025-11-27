<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\LandingSubscription;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\XenditService;
use App\Services\RegistrationProvisioningService;
use Illuminate\Validation\ValidationException;

class LandingController extends Controller
{
    public function index()
    {
        $plans = Plan::active()->ordered()->get();
        
        // Get current user's tenant and active plan (for dynamic pricing UI)
        $currentPlan = null;
        $tenant = null;
        
        if (Auth::check()) {
            $user = Auth::user();
            $tenant = $user->currentTenant();
            
            if ($tenant) {
                // Use direct plan relationship which is the source of truth
                $currentPlan = $tenant->plan;
                
                // Fallback logic is handled by the migration/seeder, but keeping safe check
                if (! $currentPlan) {
                     // Try to get from active subscription
                     $activeSubscription = $tenant->activeSubscription();
                     $currentPlan = $activeSubscription?->plan;
                }
            }
        }
        
        return view('landing.xpresspos', [
            'title' => 'XpressPOS - AI Maksimalkan Bisnismu',
            'plans' => $plans,
            'currentPlan' => $currentPlan,
            'tenant' => $tenant,
        ]);
    }

    public function showLogin()
    {
        return view('landing.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            $tenant = $user->currentTenant();
            
            // Redirect berdasarkan role user atau intended URL
            // Admin sistem dan super admin -> Admin panel
            // Check role tanpa team context karena ini adalah global roles
            if ($user->hasRole(['admin_sistem', 'super_admin'])) {
                // Use relative URL to respect current request port
                return redirect()->intended('/admin')
                    ->with('success', 'Login berhasil! Selamat datang kembali.');
            }

            if ($tenant && ! $tenant->plan_id) {
                return redirect()->route('landing.pricing')
                    ->with('warning', 'Pilih paket terlebih dahulu untuk memulai trial 30 hari.');
            }
            
            // Owner dan role lainnya -> Owner panel atau intended URL (e.g., checkout)
            // Use relative URL to respect current request port
            return redirect()->intended('/owner')
                ->with('success', 'Login berhasil! Selamat datang kembali.');
        }

        throw ValidationException::withMessages([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ]);
    }

    public function showRegister()
    {
        return view('landing.auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.string' => 'Nama lengkap harus berupa teks.',
            'name.max' => 'Nama lengkap maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan gunakan email lain atau login.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(), // Auto-verify for now (implement email verification later if needed)
        ]);

        // Assign default role
        $user->assignRole('owner');

        // Auto-provision tenant + store for new user
        app(RegistrationProvisioningService::class)->provisionFor($user);

        return redirect()->route('login')
            ->with('success', 'Akun berhasil dibuat. Silakan login untuk melanjutkan.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('landing.home');
    }

    /**
     * Show pricing page with available plans.
     */
    public function showPricing()
    {
        $plans = Plan::active()->ordered()->get();
        
        // Get current user's tenant and active plan (for dynamic pricing UI)
        $currentPlan = null;
        $tenant = null;
        
        if (Auth::check()) {
            $user = Auth::user();
            $tenant = $user->currentTenant();
            
            if ($tenant) {
                $currentPlan = $tenant->plan;
                // Fallback if plan not set on tenant yet
                if (!$currentPlan) {
                $activeSubscription = $tenant->activeSubscription();
                $currentPlan = $activeSubscription?->plan;
                }
            }
        }
        
        return view('landing.pricing', compact('plans', 'currentPlan', 'tenant'));
    }

    /**
     * Start free trial for selected plan.
     */
    public function startTrial(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('landing.login')
                ->with('error', 'Silakan login terlebih dahulu untuk memulai trial.');
        }

        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $user = Auth::user();
        $tenant = $user->currentTenant();

        if (! $tenant) {
            return redirect()->route('landing.pricing')
                ->with('error', 'Tenant tidak ditemukan. Silakan hubungi support.');
        }

        $activeSubscription = $tenant->activeSubscription();

        if ($activeSubscription && ! $activeSubscription->hasExpired()) {
            return redirect('/owner')
                ->with('info', 'Anda sudah memiliki langganan aktif.');
        }

        $trialDays = (int) config('xendit.subscription.trial_days', 30);
        $trialEndsAt = now()->addDays($trialDays);

        DB::transaction(function () use ($tenant, $plan, $trialEndsAt) {
            $tenant->subscriptions()
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'ends_at' => now(),
                ]);

            $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'starts_at' => now()->toDateString(),
                'ends_at' => $trialEndsAt->toDateString(),
                'trial_ends_at' => $trialEndsAt->toDateString(),
                'amount' => 0,
                'metadata' => [
                    'source' => 'trial',
                ],
            ]);

            $tenant->update([
                'plan_id' => $plan->id,
                'status' => 'active',
            ]);
        });

        return redirect('/owner')->with(
            'success',
            "Trial {$plan->name} aktif hingga {$trialEndsAt->translatedFormat('d M Y')}."
        );
    }

    /**
     * Show checkout page for selected plan.
     * 
     * AUTHENTICATED FLOW: Support plan_id (integer) atau plan (slug) untuk backward compatibility.
     */
    public function showCheckout(Request $request)
    {
        $request->validate([
            'plan_id' => 'nullable|exists:plans,id', // Primary: plan_id (integer)
            'plan' => 'nullable|string', // Secondary: slug (for backward compatibility)
            'billing' => 'required|in:monthly,yearly',
            'coupon_code' => 'nullable|string|max:255',
        ]);

        // Prioritize plan_id over slug
        if ($request->has('plan_id')) {
            $plan = Plan::findOrFail($request->plan_id);
        } elseif ($request->has('plan')) {
            $plan = Plan::where('slug', $request->plan)->firstOrFail();
        } else {
            return redirect()->route('landing.pricing')
                ->with('error', 'Silakan pilih plan terlebih dahulu.');
        }

        $billing = $request->billing;
        $price = $billing === 'yearly' ? $plan->annual_price : $plan->price;
        $originalPrice = $price;
        
        // Apply coupon if provided
        $couponData = null;
        $discountAmount = 0;
        if ($request->coupon_code) {
            $tenantId = null;
            if (Auth::check()) {
                $tenant = Auth::user()->currentTenant();
                $tenantId = $tenant?->id;
            }
            
            $couponService = app(\App\Services\SubscriptionCouponService::class);
            $couponResult = $couponService->validateAndCalculate(
                strtoupper(trim($request->coupon_code)),
                $price,
                $tenantId,
                $plan
            );
            
            if ($couponResult['valid']) {
                $price = $couponResult['final_amount'];
                $discountAmount = $couponResult['discount_amount'];
                $couponData = [
                    'code' => $couponResult['voucher']->code,
                    'discount_amount' => $couponResult['discount_amount'],
                    'discount_percentage' => $couponResult['discount_percentage'],
                    'promotion_name' => $couponResult['promotion']->name ?? null,
                ];
            }
        }
        
        $total = $price; // No tax for now

        // Detect upgrade/downgrade for authenticated users
        $currentPlan = null;
        $isUpgrade = false;
        $isDowngrade = false;
        $changeType = 'new'; // new, upgrade, downgrade
        
        if (Auth::check()) {
            $user = Auth::user();
            $tenant = $user->currentTenant();
            
            if ($tenant) {
                $currentPlan = $tenant->plan;

                if (!$currentPlan) {
                    $activeSubscription = $tenant->activeSubscription();
                    $currentPlan = $activeSubscription?->plan;
                }

                if (!$currentPlan) {
                    $latestSubscription = $tenant->subscriptions()
                        ->with('plan')
                        ->latest('created_at')
                        ->first();
                    $currentPlan = $latestSubscription?->plan;
                }
                
                if ($currentPlan) {
                    if ($plan->sort_order > $currentPlan->sort_order) {
                        $isUpgrade = true;
                        $changeType = 'upgrade';
                    } elseif ($plan->sort_order < $currentPlan->sort_order) {
                        $isDowngrade = true;
                        $changeType = 'downgrade';
                    }
                }
            }
        }

        return view('landing.checkout', [
            'plan' => $plan,
            'planId' => $plan->id, // Use plan_id (integer) instead of slug
            'billing' => $billing,
            'price' => $price,
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'coupon' => $couponData,
            'coupon_code' => $request->coupon_code,
            'tax' => 0, // No tax
            'total' => $total,
            'currentPlan' => $currentPlan,
            'isUpgrade' => $isUpgrade,
            'isDowngrade' => $isDowngrade,
            'changeType' => $changeType,
        ]);
    }

    /**
     * Process subscription registration and create payment.
     * 
     * AUTHENTICATED FLOW: User harus login dulu, wajib isi user_id & tenant_id.
     */
    public function processSubscription(Request $request)
    {
        // Wajib authenticated untuk checkout
        if (!Auth::check()) {
            return redirect()->route('landing.login')
                ->with('error', 'Silakan login terlebih dahulu untuk melanjutkan checkout.');
        }

        $user = Auth::user();
        $tenant = $user->currentTenant();

        if (!$tenant) {
            return redirect()->route('landing.login')
                ->with('error', 'Anda belum memiliki tenant. Silakan hubungi administrator.');
        }

        $request->validate([
            'plan_id' => 'required|exists:plans,id', // Changed to plan_id (integer)
            'billing_cycle' => 'required|in:monthly,yearly'
        ]);

        try {
            DB::beginTransaction();

            // Get plan by ID (not slug)
            $plan = Plan::findOrFail($request->plan_id);
            
            // Calculate amount
            $amount = $request->billing_cycle === 'yearly' ? $plan->annual_price : $plan->price;
            $totalAmount = $amount; // No tax for now

            // Detect upgrade/downgrade
            $currentPlan = $tenant->plan;
            
            if (!$currentPlan) {
                $activeSubscription = $tenant->activeSubscription();
                $currentPlan = $activeSubscription?->plan;
            }

            if (!$currentPlan) {
                $latestSubscription = $tenant->subscriptions()
                    ->with('plan')
                    ->latest('created_at')
                    ->first();
                $currentPlan = $latestSubscription?->plan;
            }
            $isUpgrade = false;
            $isDowngrade = false;
            
            if ($currentPlan) {
                if ($plan->sort_order > $currentPlan->sort_order) {
                    $isUpgrade = true;
                } elseif ($plan->sort_order < $currentPlan->sort_order) {
                    $isDowngrade = true;
                }
            }

            // Create landing subscription dengan authenticated flow
            $landingSubscription = LandingSubscription::create([
                // Authenticated checkout fields (WAJIB)
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $request->billing_cycle,
                'payment_amount' => $totalAmount,
                
                // Status tracking
                'status' => 'pending',
                'stage' => 'payment_pending',
                'payment_status' => 'pending',
                
                // Upgrade/Downgrade tracking
                'is_upgrade' => $isUpgrade,
                'is_downgrade' => $isDowngrade,
                'previous_plan_id' => $currentPlan?->id,
                
                // Legacy fields (optional, untuk backward compatibility)
                'email' => $user->email,
                'name' => $user->name,
                'business_name' => $tenant->name,
                'meta' => [
                    'source' => 'dashboard',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'change_type' => $isUpgrade ? 'upgrade' : ($isDowngrade ? 'downgrade' : 'new'),
                ],
            ]);

            DB::commit();

            // Redirect directly to payment page (payment method will be selected there)
            return redirect()->route('landing.payment', [
                'subscription_id' => $landingSubscription->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return back()->withErrors([
                'error' => 'Terjadi kesalahan saat memproses data: ' . $e->getMessage()
            ])->withInput();
        }
    }

    /**
     * Show payment page for payment method selection.
     */
    public function showPayment(Request $request)
    {
        $subscriptionId = $request->subscription_id;
        $subscription = LandingSubscription::findOrFail($subscriptionId);
        
        // Ensure subscription is in correct state for payment
        if ($subscription->payment_status !== 'pending') {
            return redirect()->route('landing.payment.success', ['subscription_id' => $subscription->id])
                ->with('info', 'Pembayaran sudah diproses sebelumnya.');
        }

        // Get payment methods
        $paymentMethods = [
            'bank_transfer' => [
                'name' => 'Transfer Bank',
                'description' => 'BCA, BNI, BRI, Mandiri, Permata',
                'icon' => 'bank'
            ],
            'e_wallet' => [
                'name' => 'E-Wallet',
                'description' => 'OVO, DANA, LinkAja, ShopeePay',
                'icon' => 'wallet'
            ],
            'qris' => [
                'name' => 'QRIS',
                'description' => 'Scan QR Code untuk pembayaran',
                'icon' => 'qr-code'
            ],
            'credit_card' => [
                'name' => 'Kartu Kredit',
                'description' => 'Visa, Mastercard, JCB',
                'icon' => 'credit-card'
            ]
        ];

        return view('landing.payment', [
            'subscription' => $subscription,
            'paymentMethods' => $paymentMethods
        ]);
    }

    /**
     * Process payment with selected payment method.
     */
    public function processPayment(Request $request)
    {
        $request->validate([
            'subscription_id' => 'required|exists:landing_subscriptions,id',
            'payment_method' => 'required|in:bank_transfer,e_wallet,qris,credit_card',
        ]);

        try {
            $subscription = LandingSubscription::findOrFail($request->subscription_id);

            // Check if already paid
            if ($subscription->payment_status === 'paid') {
                return redirect()->route('landing.payment.success', ['subscription_id' => $subscription->id]);
            }

            $xenditService = app(XenditService::class);

            // Create Xendit invoice with selected payment method
            $invoiceData = [
                'external_id' => 'SUB-' . $subscription->id . '-' . time(),
                'amount' => (int) $subscription->payment_amount,
                'description' => "XpressPOS {$subscription->plan_id} subscription - {$subscription->billing_cycle}",
                'customer' => [
                    'given_names' => $subscription->name,
                    'email' => $subscription->email,
                    'mobile_number' => $subscription->phone,
                ],
                'payment_methods' => [$request->payment_method],
            ];

            $xenditResponse = $xenditService->createInvoice($invoiceData);

            if (!$xenditResponse['success']) {
                return back()->withErrors(['error' => 'Gagal membuat pembayaran: ' . $xenditResponse['error']]);
            }

            // Update subscription with payment info
            $subscription->update([
                'xendit_invoice_id' => $xenditResponse['data']['id'],
                'payment_status' => 'pending'
            ]);

            // Redirect to Xendit payment page
            return redirect($xenditResponse['data']['invoice_url']);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    /**
     * Show payment success page.
     */
    public function paymentSuccess(Request $request)
    {
        $subscriptionId = $request->subscription_id;
        $subscription = LandingSubscription::find($subscriptionId);

        if (!$subscription) {
            return redirect()->route('landing.home')->with('error', 'Subscription not found.');
        }

        // Check payment status from Xendit (important for simulated payments)
        // This ensures we catch payments even if webhook didn't fire
        $payment = $subscription->latestSubscriptionPayment;
        
        // If no payment record exists but we have xendit_invoice_id, create it
        if (!$payment && $subscription->xendit_invoice_id) {
            try {
                $xenditService = app(\App\Services\XenditService::class);
                $invoiceResult = $xenditService->getInvoice($subscription->xendit_invoice_id);
                
                if ($invoiceResult['success']) {
                    $invoiceData = $invoiceResult['data'];
                    
                    // Create payment record from Xendit invoice
                    // For dummy invoices, use subscription amount if invoice amount doesn't match
                    $invoiceAmount = $invoiceData['amount'] ?? $subscription->payment_amount;
                    if (str_starts_with($subscription->xendit_invoice_id, 'dummy_') && $invoiceAmount != $subscription->payment_amount) {
                        $invoiceAmount = $subscription->payment_amount;
                        $invoiceData['amount'] = $subscription->payment_amount;
                        $invoiceData['paid_amount'] = $subscription->payment_amount;
                    }
                    
                    $payment = \App\Models\SubscriptionPayment::create([
                        'landing_subscription_id' => $subscription->id,
                        'xendit_invoice_id' => $subscription->xendit_invoice_id,
                        'external_id' => $invoiceData['external_id'] ?? 'LS-' . $subscription->id . '-' . time(),
                        'amount' => $invoiceAmount,
                        'status' => $this->mapXenditStatus($invoiceData['status'] ?? 'PENDING'),
                        'gateway_response' => $invoiceData,
                        'paid_at' => isset($invoiceData['paid_at']) ? ($invoiceData['status'] === 'PAID' ? now() : null) : null,
                        'expires_at' => isset($invoiceData['expiry_date']) ? \Carbon\Carbon::parse($invoiceData['expiry_date']) : null,
                    ]);
                    
                    \Log::info('Created payment record from Xendit on success page', [
                        'landing_subscription_id' => $subscription->id,
                        'payment_id' => $payment->id,
                    ]);
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to create payment record from Xendit', [
                    'landing_subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        if ($payment && $subscription->xendit_invoice_id) {
            try {
                $xenditService = app(\App\Services\XenditService::class);
                $invoiceResult = $xenditService->getInvoice($subscription->xendit_invoice_id);
                
                if ($invoiceResult['success'] && isset($invoiceResult['data']['status'])) {
                    $xenditStatus = $invoiceResult['data']['status'];
                    
                    \Log::info('Checking Xendit payment status on success page', [
                        'landing_subscription_id' => $subscription->id,
                        'payment_id' => $payment->id,
                        'xendit_status' => $xenditStatus,
                        'current_payment_status' => $payment->status,
                        'current_subscription_status' => $subscription->payment_status,
                    ]);
                    
                    // Update payment status if it's paid on Xendit but not in our database
                    // For dummy invoices in development, this will always be PAID
                    // SETTLED is also considered PAID
                    if (in_array(strtoupper($xenditStatus), ['PAID', 'SETTLED'])) {
                        // For dummy invoices, ensure amount matches subscription
                        if (str_starts_with($subscription->xendit_invoice_id, 'dummy_')) {
                            $invoiceResult['data']['amount'] = $subscription->payment_amount;
                            $invoiceResult['data']['paid_amount'] = $subscription->payment_amount;
                        }
                        
                        // Always update if Xendit says PAID, regardless of current status
                        $payment->updateFromXenditCallback($invoiceResult['data']);
                        $payment->refresh();
                        
                        // Update landing subscription status
                        $subscription->update([
                            'payment_status' => 'paid',
                            'status' => 'paid',
                            'stage' => 'payment_completed',
                            'paid_at' => $payment->paid_at ?? now(),
                        ]);
                        $subscription->refresh();
                        
                        \Log::info('Payment status synced from Xendit on success page', [
                            'landing_subscription_id' => $subscription->id,
                            'payment_id' => $payment->id,
                            'xendit_status' => $xenditStatus,
                            'new_payment_status' => $payment->status,
                            'new_subscription_status' => $subscription->payment_status,
                        ]);
                    } else {
                        \Log::warning('Xendit invoice is not PAID yet', [
                            'landing_subscription_id' => $subscription->id,
                            'xendit_status' => $xenditStatus,
                        ]);
                    }
                } else {
                    \Log::warning('Failed to get invoice status from Xendit', [
                        'landing_subscription_id' => $subscription->id,
                        'success' => $invoiceResult['success'] ?? false,
                        'error' => $invoiceResult['error'] ?? 'Unknown error',
                    ]);
                }
                } catch (\Exception $e) {
                \Log::warning('Failed to check Xendit payment status on success page', [
                    'landing_subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } elseif (!$payment && $subscription->xendit_invoice_id) {
            // If no payment record but we have xendit_invoice_id, try to create it and check status
            try {
                $xenditService = app(\App\Services\XenditService::class);
                $invoiceResult = $xenditService->getInvoice($subscription->xendit_invoice_id);
                
                if ($invoiceResult['success'] && isset($invoiceResult['data']['status'])) {
                    $invoiceData = $invoiceResult['data'];
                    
                    // Create payment record
                    $payment = \App\Models\SubscriptionPayment::create([
                        'landing_subscription_id' => $subscription->id,
                        'xendit_invoice_id' => $subscription->xendit_invoice_id,
                        'external_id' => $invoiceData['external_id'] ?? 'LS-' . $subscription->id . '-' . time(),
                        'amount' => $invoiceData['amount'] ?? $subscription->payment_amount,
                        'status' => $this->mapXenditStatus($invoiceData['status'] ?? 'PENDING'),
                        'gateway_response' => $invoiceData,
                        'paid_at' => ($invoiceData['status'] === 'PAID' && isset($invoiceData['paid_at'])) 
                            ? \Carbon\Carbon::parse($invoiceData['paid_at']) 
                            : (($invoiceData['status'] === 'PAID') ? now() : null),
                        'expires_at' => isset($invoiceData['expiry_date']) 
                            ? \Carbon\Carbon::parse($invoiceData['expiry_date']) 
                            : null,
                    ]);
                    
                    // Update subscription if paid
                    if ($invoiceData['status'] === 'PAID') {
                        $subscription->update([
                            'payment_status' => 'paid',
                            'status' => 'paid',
                            'stage' => 'payment_completed',
                            'paid_at' => $payment->paid_at ?? now(),
                        ]);
                        $subscription->refresh();
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to create payment record from Xendit invoice on success page', [
                    'landing_subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Auto-provision account if payment is successful and not yet provisioned
        // Check both provisioned_user_id (legacy) and subscription_id (new)
        $needsProvisioning = $subscription->payment_status === 'paid' && 
                           !$subscription->provisioned_user_id && 
                           !$subscription->subscription_id;
        
        if ($needsProvisioning) {
            // Try to find SubscriptionPayment to trigger provisioning
            if (!$payment) {
                $payment = $subscription->latestSubscriptionPayment;
            }
            
            // If payment is not paid but subscription says paid, mark payment as paid
            // This handles cases where status was updated but payment record wasn't
            if ($payment && !$payment->isPaid() && $subscription->payment_status === 'paid') {
                $payment->markAsPaid();
                $payment->refresh();
            }
            
            if ($payment && $payment->isPaid()) {
                try {
                    $provisioningService = app(\App\Services\SubscriptionProvisioningService::class);
                    $result = $provisioningService->provisionFromPaidLandingSubscription($subscription, $payment);
                    
                    if ($result['success']) {
                        // Auto-login the user and redirect to owner dashboard
                        $user = \App\Models\User::find($result['user_id'] ?? $result['user']->id ?? null);
                        
                        if ($user) {
                            auth()->login($user);
                            
                            return redirect()
                                ->route('filament.owner.pages.dashboard')
                                ->with('success', 'Welcome! Your subscription is now active.');
                        }
                        
                        // Fallback: show success page with login info
                        $subscription->refresh();
                        
                        return view('landing.payment-success', [
                            'subscription' => $subscription,
                            'provisioning' => $result,
                            'showLoginInfo' => true,
                            'loginUrl' => $result['login_url'] ?? config('app.owner_url', '/owner'),
                            'temporaryPassword' => $result['temporary_password'] ?? null
                        ]);
                    } else {
                        \Log::error('Auto-provisioning failed', [
                            'subscription_id' => $subscription->id, 
                            'error' => $result['error'] ?? 'Unknown error'
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Exception during auto-provisioning', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } else {
                // Fallback to old method (for legacy subscriptions without payment records)
                try {
                    $provisioningService = app(\App\Services\SubscriptionProvisioningService::class);
                    $result = $provisioningService->provisionSubscription($subscription);
                    
                    if ($result['success']) {
                        $user = \App\Models\User::find($result['user_id'] ?? null);
                        
                        if ($user) {
                            auth()->login($user);
                            
                            return redirect()
                                ->route('filament.owner.pages.dashboard')
                                ->with('success', 'Welcome! Your subscription is now active.');
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Exception during fallback provisioning', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // If already provisioned, try to auto-login
        if ($subscription->subscription_id || $subscription->provisioned_user_id) {
            $user = null;
            
            // For authenticated users, use the user from landing subscription
            if ($subscription->user_id) {
                $user = \App\Models\User::find($subscription->user_id);
            } elseif ($subscription->provisioned_user_id) {
                $user = \App\Models\User::find($subscription->provisioned_user_id);
            }
            
            if ($user && !auth()->check()) {
                auth()->login($user);
                return redirect()
                    ->route('filament.owner.pages.dashboard')
                    ->with('success', 'Welcome back! Your payment was successful.');
            }
        }

        return view('landing.payment-success', [
            'subscription' => $subscription,
            'showLoginInfo' => ($subscription->provisioned_user_id || $subscription->subscription_id) ? true : false,
            'loginUrl' => $subscription->onboarding_url ?? config('app.owner_url', '/owner')
        ]);
    }
    
    /**
     * Map Xendit status to payment status
     */
    private function mapXenditStatus(string $xenditStatus): string
    {
        return match(strtoupper($xenditStatus)) {
            'PAID', 'SETTLED' => 'paid',
            'PENDING' => 'pending',
            'EXPIRED' => 'expired',
            'FAILED' => 'failed',
            default => 'pending'
        };
    }

    /**
     * Show payment failed page.
     */
    public function paymentFailed(Request $request)
    {
        $subscriptionId = $request->subscription_id;
        $subscription = LandingSubscription::find($subscriptionId);

        return view('landing.payment-failed', [
            'subscription' => $subscription
        ]);
    }

    /**
     * Show customer dashboard.
     */
    public function customerDashboard(Request $request)
    {
        $email = $request->email;
        
        if (!$email) {
            return redirect()->route('landing.home');
        }

        $subscriptions = LandingSubscription::where('email', $email)
            ->with('subscriptionPayments')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('landing.customer-dashboard', [
            'subscriptions' => $subscriptions,
            'email' => $email
        ]);
    }

    /**
     * Show checkout step 2 - Business Information.
     */
    public function showCheckoutStep2(Request $request)
    {
        $request->validate([
            'plan_id' => 'nullable|exists:plans,id', // Primary: plan_id (integer)
            'plan' => 'nullable|string', // Secondary: slug (for backward compatibility)
            'billing' => 'required|in:monthly,yearly',
            'coupon_code' => 'nullable|string|max:255',
        ]);

        // Prioritize plan_id over slug
        if ($request->has('plan_id')) {
            $selectedPlan = Plan::findOrFail($request->plan_id);
        } elseif ($request->has('plan')) {
            $selectedPlan = Plan::where('slug', $request->plan)->firstOrFail();
        } else {
            return redirect()->route('landing.checkout')
                ->with('error', 'Silakan pilih plan terlebih dahulu.');
        }

        $billing = $request->billing;
        $price = $billing === 'yearly' ? $selectedPlan->annual_price : $selectedPlan->price;
        
        // Apply coupon if provided
        $couponData = null;
        $discountAmount = 0;
        $originalPrice = $price;
        
        if ($request->coupon_code) {
            $tenantId = null;
            if (Auth::check()) {
                $tenant = Auth::user()->currentTenant();
                $tenantId = $tenant?->id;
            }
            
            $couponService = app(\App\Services\SubscriptionCouponService::class);
            $couponResult = $couponService->validateAndCalculate(
                strtoupper(trim($request->coupon_code)),
                $price,
                $tenantId,
                $selectedPlan
            );
            
            if ($couponResult['valid']) {
                $price = $couponResult['final_amount'];
                $discountAmount = $couponResult['discount_amount'];
                $couponData = [
                    'code' => $couponResult['voucher']->code,
                    'discount_amount' => $couponResult['discount_amount'],
                    'discount_percentage' => $couponResult['discount_percentage'],
                    'promotion_name' => $couponResult['promotion']->name ?? null,
                ];
            }
        }
        
        // No tax calculation
        $total = $price; // No tax

        return view('landing.business-information', [
            'plan' => $selectedPlan,
            'planId' => $selectedPlan->id, // Use plan_id (integer) instead of slug
            'billing' => $billing,
            'price' => $price,
            'original_price' => $originalPrice,
            'discount_amount' => $discountAmount,
            'coupon' => $couponData,
            'coupon_code' => $request->coupon_code,
            'tax' => 0, // No tax
            'total' => $total
        ]);
    }

    /**
     * Process checkout step 2 - Store business information and process payment directly.
     * 
     * AUTHENTICATED FLOW: User harus login dulu, wajib isi user_id & tenant_id.
     */
    public function processCheckoutStep2(Request $request)
    {
        // Wajib authenticated untuk checkout
        if (!Auth::check()) {
            return redirect()->route('landing.login')
                ->with('error', 'Silakan login terlebih dahulu untuk melanjutkan checkout.');
        }

        $user = Auth::user();
        $tenant = $user->currentTenant();

        if (!$tenant) {
            return redirect()->route('landing.login')
                ->with('error', 'Anda belum memiliki tenant. Silakan hubungi administrator.');
        }

        $request->validate([
            'plan_id' => 'required|exists:plans,id', // Changed to plan_id (integer)
            'billing_cycle' => 'required|in:monthly,yearly',
            'coupon_code' => 'nullable|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Get plan by ID
            $plan = Plan::findOrFail($request->plan_id);
            
            // Calculate base amount
            $amount = $request->billing_cycle === 'yearly' ? $plan->annual_price : $plan->price;
            $totalAmount = $amount; // No tax for now
            
            // Apply coupon if provided
            $couponData = null;
            $voucherId = null;
            if ($request->coupon_code) {
                $couponService = app(\App\Services\SubscriptionCouponService::class);
                $couponResult = $couponService->validateAndCalculate(
                    strtoupper(trim($request->coupon_code)),
                    $amount,
                    $tenant->id,
                    $plan
                );
                
                if ($couponResult['valid']) {
                    $totalAmount = $couponResult['final_amount'];
                    $couponData = [
                        'code' => $couponResult['voucher']->code,
                        'discount_amount' => $couponResult['discount_amount'],
                        'original_amount' => $couponResult['original_amount'],
                        'final_amount' => $couponResult['final_amount'],
                        'discount_percentage' => $couponResult['discount_percentage'],
                        'promotion_name' => $couponResult['promotion']->name ?? null,
                    ];
                    $voucherId = $couponResult['voucher']->id;
                } else {
                    return back()->withErrors(['coupon_code' => $couponResult['error'] ?? 'Kode kupon tidak valid']);
                }
            }

            // Create landing subscription dengan authenticated flow
            $landingSubscription = LandingSubscription::create([
                // Authenticated checkout fields (WAJIB)
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $request->billing_cycle,
                'payment_amount' => $totalAmount,
                
                // Status tracking
                'status' => 'pending',
                'stage' => 'payment_pending',
                'payment_status' => 'pending',
                
                // Legacy fields (optional, untuk backward compatibility)
                'email' => $user->email,
                'name' => $user->name,
                'business_name' => $tenant->name,
                'meta' => array_merge([
                    'source' => 'dashboard',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'payment_method' => 'xendit_hosted',
                ], $couponData ? ['coupon' => $couponData, 'voucher_id' => $voucherId] : []),
            ]);

            // Create Xendit invoice with all payment methods available
            $xenditService = app(XenditService::class);
            
            $invoiceData = [
                'external_id' => 'XPOS-SUB-' . $landingSubscription->id . '-' . time(),
                'amount' => (int) $totalAmount, // Ensure integer
                'description' => "XpressPOS {$plan->name} subscription ({$request->billing_cycle})",
                'customer' => [
                    'given_names' => $user->name,
                    'email' => $user->email,
                    'mobile_number' => $tenant->phone ?? $user->email, // Use tenant phone or fallback
                ],
                // payment_methods will be determined by XenditService based on environment
                'success_redirect_url' => config('xendit.invoice.success_redirect_url') . '?subscription_id=' . $landingSubscription->id,
                'failure_redirect_url' => config('xendit.invoice.failure_redirect_url') . '?subscription_id=' . $landingSubscription->id
            ];

            $xenditResponse = $xenditService->createInvoice($invoiceData);

            if (!$xenditResponse['success']) {
                throw new \Exception('Failed to create payment invoice: ' . $xenditResponse['error']);
            }

            // Update subscription with payment info
            $landingSubscription->update([
                'xendit_invoice_id' => $xenditResponse['data']['id']
            ]);

            DB::commit();

            // Redirect to Xendit hosted payment page (works in both local and production)
            return redirect($xenditResponse['data']['invoice_url']);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment processing error: ' . $e->getMessage());
            
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    /**
     * Show checkout step 3 - Payment Method Selection.
     */
    public function showCheckoutStep3(Request $request)
    {
        $checkoutData = session('checkout_data');
        
        if (!$checkoutData) {
            return redirect()->route('landing.checkout')->with('error', 'Session expired. Please start again.');
        }

        $request->validate([
            'plan' => 'required|in:basic,pro,enterprise',
            'billing' => 'required|in:monthly,yearly'
        ]);

        $plan = Plan::where('slug', $request->plan)->firstOrFail();
        $amount = $request->billing === 'yearly' ? $plan->annual_price : $plan->price;
        // No tax calculation
        $totalAmount = $amount; // No tax

        // Get payment methods
        $paymentMethods = [
            'bank_transfer' => [
                'name' => 'Transfer Bank',
                'description' => 'BCA, BNI, BRI, Mandiri',
                'icon' => 'bank'
            ],
            'e_wallet' => [
                'name' => 'E-Wallet',
                'description' => 'OVO, DANA, LinkAja, ShopeePay',
                'icon' => 'wallet'
            ],
            'qris' => [
                'name' => 'QRIS',
                'description' => 'Scan QR Code',
                'icon' => 'qr-code'
            ],
            'credit_card' => [
                'name' => 'Kartu Kredit',
                'description' => 'Visa, Mastercard, JCB',
                'icon' => 'credit-card'
            ]
        ];

        return view('landing.payment-method', [
            'checkoutData' => $checkoutData,
            'paymentMethods' => $paymentMethods,
            'planId' => $request->plan,
            'billing' => $request->billing,
            'amount' => $totalAmount
        ]);
    }

    /**
     * Process checkout step 3 - Create subscription and redirect to Xendit.
     */
    public function processCheckoutStep3(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|in:bank_transfer,e_wallet,qris,credit_card'
        ]);

        $checkoutData = session('checkout_data');
        
        if (!$checkoutData) {
            return redirect()->route('landing.checkout')->with('error', 'Session expired. Please start again.');
        }

        try {
            DB::beginTransaction();

            // Calculate amount from database
            $plan = Plan::where('slug', $checkoutData['plan_id'])->firstOrFail();
            $amount = $checkoutData['billing_cycle'] === 'yearly' ? $plan->annual_price : $plan->price;
            // No tax calculation
            $totalAmount = $amount; // No tax

            // Create landing subscription
            $landingSubscription = LandingSubscription::create([
                'name' => $checkoutData['name'],
                'email' => $checkoutData['email'],
                'phone' => $checkoutData['phone'],
                'company' => $checkoutData['business_name'], // Use company field for business_name
                'plan' => $checkoutData['plan_id'],
                'payment_amount' => $totalAmount,
                'status' => 'pending_payment',
                'stage' => 'payment_pending',
                'payment_status' => 'pending',
                'meta' => json_encode([
                    'business_type' => $checkoutData['business_type'],
                    'billing_cycle' => $checkoutData['billing_cycle'],
                    'payment_method' => $request->payment_method
                ])
            ]);

            // Create Xendit invoice
            $xenditService = app(XenditService::class);
            
            $invoiceData = [
                'external_id' => 'XPOS-SUB-' . $landingSubscription->id . '-' . time(),
                'amount' => (int) $totalAmount, // Ensure integer
                'description' => "XpressPOS {$checkoutData['plan_id']} subscription ({$checkoutData['billing_cycle']})",
                'customer' => [
                    'given_names' => $checkoutData['name'],
                    'email' => $checkoutData['email'],
                    'mobile_number' => $checkoutData['phone'], // Should already be in E.164 format
                ],
                // payment_methods will be determined by XenditService based on environment
                'success_redirect_url' => config('xendit.invoice.success_redirect_url') . '?subscription_id=' . $landingSubscription->id,
                'failure_redirect_url' => config('xendit.invoice.failure_redirect_url') . '?subscription_id=' . $landingSubscription->id
            ];

            $xenditResponse = $xenditService->createInvoice($invoiceData);

            if (!$xenditResponse['success']) {
                throw new \Exception('Failed to create payment invoice: ' . $xenditResponse['error']);
            }

            // Update subscription with payment info
            $landingSubscription->update([
                'xendit_invoice_id' => $xenditResponse['data']['id']
            ]);

            DB::commit();

            // Clear session data
            session()->forget('checkout_data');

            // In development mode, you can choose to show Xendit or simulate
            // Comment out this block to show actual Xendit payment page in development
            /*
            if (config('xendit.is_production') === false) {
                // Simulate payment processing delay
                sleep(1);
                
                // Update payment status to paid
                $landingSubscription->update([
                    'payment_status' => 'paid',
                    'paid_at' => now()
                ]);
                
                return redirect()->route('landing.payment.success', ['subscription_id' => $landingSubscription->id])
                    ->with('success', 'Pembayaran berhasil! (Development Mode)');
            }
            */

            // In production, redirect to Xendit hosted payment page
            return redirect($xenditResponse['data']['invoice_url']);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Payment processing error: ' . $e->getMessage());
            
            return back()->withErrors(['error' => 'Terjadi kesalahan: ' . $e->getMessage()]);
        }
    }

    /**
     * Show Privacy Policy page.
     */
    public function showPrivacyPolicy()
    {
        return view('landing.privacy-policy', [
            'title' => 'Kebijakan Privasi - XpressPOS'
        ]);
    }

    /**
     * Show Terms and Conditions page.
     */
    public function showTermsAndConditions()
    {
        return view('landing.terms-and-conditions', [
            'title' => 'Syarat dan Ketentuan - XpressPOS'
        ]);
    }

    /**
     * Show Cookie Policy page.
     */
    public function showCookiePolicy()
    {
        return view('landing.cookie-policy', [
            'title' => 'Kebijakan Cookie - XpressPOS'
        ]);
    }
}