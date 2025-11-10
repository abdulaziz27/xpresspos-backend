<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\LandingSubscription;
use App\Models\Plan;
use App\Services\XenditService;
use Illuminate\Validation\ValidationException;

class LandingController extends Controller
{
    public function index()
    {
        $plans = Plan::active()->ordered()->get();
        
        return view('landing.xpresspos', [
            'title' => 'XpressPOS - AI Maksimalkan Bisnismu',
            'plans' => $plans
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
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            $user = Auth::user();
            
            // Redirect berdasarkan role user
            // Admin sistem dan super admin -> Admin panel
            // Check role tanpa team context karena ini adalah global roles
            if ($user->hasRole(['admin_sistem', 'super_admin'])) {
                return redirect()->to(config('app.admin_url', '/admin'));
            }
            
            // Owner dan role lainnya -> Owner panel
            return redirect()->to(config('app.owner_url', '/owner'));
        }

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
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
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Assign default role
        $user->assignRole('owner');

        Auth::login($user);

        // Redirect ke owner panel untuk user baru
        return redirect()->to(config('app.owner_url', '/owner'));
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
        
        return view('landing.pricing', compact('plans'));
    }

    /**
     * Show checkout page for selected plan.
     */
    public function showCheckout(Request $request)
    {
        $request->validate([
            'plan' => 'required|in:basic,pro,enterprise',
            'billing' => 'required|in:monthly,yearly'
        ]);

        $plan = Plan::where('slug', $request->plan)->firstOrFail();
        $billing = $request->billing;
        $price = $billing === 'yearly' ? $plan->annual_price : $plan->price;
        $total = $price; // No tax for now

        return view('landing.checkout', [
            'plan' => $plan,
            'planId' => $request->plan,
            'billing' => $billing,
            'price' => $price,
            'tax' => 0, // No tax
            'total' => $total
        ]);
    }

    /**
     * Process subscription registration and create payment.
     */
    public function processSubscription(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|max:100',
            'plan_id' => 'required|in:basic,pro,enterprise',
            'billing_cycle' => 'required|in:monthly,yearly'
            // payment_method removed - will be selected in payment page
        ]);

        try {
            DB::beginTransaction();

            // Calculate amount from database
            $plan = Plan::where('slug', $request->plan_id)->firstOrFail();
            $amount = $request->billing_cycle === 'yearly' ? $plan->annual_price : $plan->price;
            // No tax calculation
            $totalAmount = $amount; // No tax

            // Create landing subscription with calculated amount
            $landingSubscription = LandingSubscription::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'business_name' => $request->business_name,
                'business_type' => $request->business_type,
                'plan_id' => $request->plan_id,
                'billing_cycle' => $request->billing_cycle,
                'payment_amount' => $totalAmount,
                'status' => 'pending_payment',
                'stage' => 'payment_pending',
                'payment_status' => 'pending'
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

        // Auto-provision account if payment is successful and not yet provisioned
        if ($subscription->payment_status === 'paid' && !$subscription->provisioned_user_id) {
            $provisioningService = app(\App\Services\SubscriptionProvisioningService::class);
            $result = $provisioningService->provisionSubscription($subscription);
            
            if ($result['success']) {
                // Auto-login the user and redirect to owner dashboard
                $user = \App\Models\User::find($result['user_id']);
                
                if ($user) {
                    auth()->login($user);
                    
                    return redirect()
                        ->route('filament.owner.pages.dashboard')
                        ->with('success', 'Welcome! Your subscription is now active. Check your email for login credentials.');
                }
                
                // Fallback: show success page with login info
                $subscription->refresh();
                
                return view('landing.payment-success', [
                    'subscription' => $subscription,
                    'provisioning' => $result,
                    'showLoginInfo' => true,
                    'loginUrl' => $result['login_url'],
                    'temporaryPassword' => $result['temporary_password']
                ]);
            } else {
                \Log::error('Auto-provisioning failed', ['subscription_id' => $subscription->id, 'error' => $result['error']]);
            }
        }

        // If already provisioned, try to auto-login
        if ($subscription->provisioned_user_id) {
            $user = \App\Models\User::find($subscription->provisioned_user_id);
            
            if ($user && !auth()->check()) {
                auth()->login($user);
                return redirect()
                    ->route('filament.owner.pages.dashboard')
                    ->with('success', 'Welcome back! Your payment was successful.');
            }
        }

        return view('landing.payment-success', [
            'subscription' => $subscription,
            'showLoginInfo' => $subscription->provisioned_user_id ? true : false,
            'loginUrl' => $subscription->onboarding_url ?? config('app.owner_url', '/owner')
        ]);
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
            'plan' => 'required|in:basic,pro,enterprise',
            'billing' => 'required|in:monthly,yearly'
        ]);

        // Get plans from database instead of hardcoded values
        $plans = Plan::active()->ordered()->get()->keyBy('slug');

        $selectedPlan = $plans[$request->plan];
        $billing = $request->billing;
        $price = $billing === 'yearly' ? $selectedPlan->annual_price : $selectedPlan->price;
        // No tax calculation
        $total = $price; // No tax

        return view('landing.business-information', [
            'plan' => $selectedPlan,
            'planId' => $request->plan,
            'billing' => $billing,
            'price' => $price,
            'tax' => 0, // No tax
            'total' => $total
        ]);
    }

    /**
     * Process checkout step 2 - Store business information and process payment directly.
     */
    public function processCheckoutStep2(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'country_code' => 'nullable|string',
            'phone' => 'required|string|max:20',
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|max:100',
            'plan_id' => 'required|in:basic,pro,enterprise',
            'billing_cycle' => 'required|in:monthly,yearly'
        ]);

        try {
            DB::beginTransaction();

            // Combine country code with phone number
            $countryCode = $request->country_code ?? '+62'; // Default to +62 if not provided
            $phoneNumber = $request->phone;
            
            // Remove leading 0 if exists
            $phoneNumber = ltrim($phoneNumber, '0');
            
            // Combine country code with phone number
            $fullPhoneNumber = $countryCode . $phoneNumber;
            
            \Log::info('Phone number processing', [
                'country_code' => $countryCode,
                'phone_input' => $request->phone,
                'phone_cleaned' => $phoneNumber,
                'full_phone' => $fullPhoneNumber
            ]);

            // Calculate amount from database
            $plan = Plan::where('slug', $request->plan_id)->firstOrFail();
            $amount = $request->billing_cycle === 'yearly' ? $plan->annual_price : $plan->price;
            // No tax calculation
            $totalAmount = $amount; // No tax

            // Create landing subscription
            $landingSubscription = LandingSubscription::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $fullPhoneNumber,
                'company' => $request->business_name, // Use company field for business_name
                'plan' => $request->plan_id,
                'payment_amount' => $totalAmount,
                'status' => 'pending_payment',
                'stage' => 'payment_pending',
                'payment_status' => 'pending',
                'meta' => json_encode([
                    'business_type' => $request->business_type,
                    'billing_cycle' => $request->billing_cycle,
                    'payment_method' => 'xendit_hosted' // Use Xendit's hosted payment page with all methods
                ])
            ]);

            // Create Xendit invoice with all payment methods available
            $xenditService = app(XenditService::class);
            
            $invoiceData = [
                'external_id' => 'XPOS-SUB-' . $landingSubscription->id . '-' . time(),
                'amount' => (int) $totalAmount, // Ensure integer
                'description' => "XpressPOS {$request->plan_id} subscription ({$request->billing_cycle})",
                'customer' => [
                    'given_names' => $request->name,
                    'email' => $request->email,
                    'mobile_number' => $fullPhoneNumber, // E.164 format: +6285211553430
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
}