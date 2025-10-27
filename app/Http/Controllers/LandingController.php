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
            
            // Redirect ke owner Filament panel
            if (app()->environment('production') && env('OWNER_URL')) {
                return redirect()->to(env('OWNER_URL'));
            } else {
                return redirect('/owner-panel');
            }
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

        if (app()->environment('production') && env('OWNER_URL')) {
            return redirect()->to(env('OWNER_URL'));
        } else {
            return redirect('/owner-panel');
        }
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
        $tax = $price * 0.11; // PPN 11%
        $total = $price + $tax;

        return view('landing.checkout', [
            'plan' => $plan,
            'planId' => $request->plan,
            'billing' => $billing,
            'price' => $price,
            'tax' => $tax,
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
            $tax = $amount * 0.11;
            $totalAmount = $amount + $tax;

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
     * Process payment method selection and redirect to Xendit.
     */
    public function processPayment(Request $request)
    {
        $request->validate([
            'subscription_id' => 'required|exists:landing_subscriptions,id',
            'payment_method' => 'required|in:bank_transfer,e_wallet,qris,credit_card'
        ]);

        try {
            $subscription = LandingSubscription::findOrFail($request->subscription_id);
            
            // Create Xendit invoice with selected payment method
            $xenditService = app(XenditService::class);
            
            $invoiceData = [
                'external_id' => 'SUB-' . $subscription->id . '-' . time(),
                'amount' => $subscription->payment_amount,
                'description' => "XpressPOS {$subscription->plan_id} subscription - {$subscription->billing_cycle}",
                'customer_name' => $subscription->name,
                'customer_email' => $subscription->email,
                'customer_phone' => $subscription->phone,
                'payment_methods' => [$request->payment_method]
            ];

            $xenditResponse = $xenditService->createInvoice($invoiceData);

            if (!$xenditResponse['success']) {
                return back()->withErrors(['error' => 'Gagal membuat pembayaran: ' . $xenditResponse['error']]);
            }

            // Update subscription with new payment info
            $subscription->update([
                'xendit_invoice_id' => $xenditResponse['data']['id'],
                'payment_status' => 'pending'
            ]);

            // In development mode, redirect to success page
            if (config('xendit.is_production') === false) {
                return redirect()->route('landing.payment.success', ['subscription_id' => $subscription->id]);
            }

            // In production, redirect to Xendit payment page
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
                // Add provisioning success data to view
                $subscription->refresh(); // Reload to get updated data
                
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

        return view('landing.payment-success', [
            'subscription' => $subscription,
            'showLoginInfo' => $subscription->provisioned_user_id ? true : false,
            'loginUrl' => $subscription->onboarding_url ?? (app()->environment('local') ? '/owner-panel' : config('domains.owner', 'https://owner.xpresspos.id'))
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

        $plans = [
            'basic' => [
                'name' => 'XpressPOS Basic',
                'monthly_price' => 99000,
                'yearly_price' => 990000,
                'features' => ['1 Lokasi Toko', 'Inventory Management', 'Laporan Dasar']
            ],
            'pro' => [
                'name' => 'XpressPOS Professional',
                'monthly_price' => 599000,
                'yearly_price' => 5990000,
                'features' => ['5 Lokasi Toko', 'Advanced Analytics', 'Multi-User Access']
            ],
            'enterprise' => [
                'name' => 'XpressPOS Enterprise',
                'monthly_price' => 1999000,
                'yearly_price' => 19990000,
                'features' => ['Unlimited Lokasi', 'Custom Integration', 'Dedicated Support']
            ]
        ];

        $selectedPlan = $plans[$request->plan];
        $billing = $request->billing;
        $price = $billing === 'yearly' ? $selectedPlan['yearly_price'] : $selectedPlan['monthly_price'];
        $tax = $price * 0.11;
        $total = $price + $tax;

        return view('landing.business-information', [
            'plan' => $selectedPlan,
            'planId' => $request->plan,
            'billing' => $billing,
            'price' => $price,
            'tax' => $tax,
            'total' => $total
        ]);
    }

    /**
     * Process checkout step 2 - Store business information.
     */
    public function processCheckoutStep2(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|max:100',
            'plan_id' => 'required|in:basic,pro,enterprise',
            'billing_cycle' => 'required|in:monthly,yearly'
        ]);

        // Store in session for step 3
        session([
            'checkout_data' => [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'business_name' => $request->business_name,
                'business_type' => $request->business_type,
                'plan_id' => $request->plan_id,
                'billing_cycle' => $request->billing_cycle
            ]
        ]);

        return redirect()->route('landing.checkout.step3', [
            'plan' => $request->plan_id,
            'billing' => $request->billing_cycle
        ]);
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
        $tax = $amount * 0.11;
        $totalAmount = $amount + $tax;

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
            $tax = $amount * 0.11;
            $totalAmount = $amount + $tax;

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
                'amount' => $totalAmount,
                'description' => "XpressPOS {$checkoutData['plan_id']} subscription ({$checkoutData['billing_cycle']})",
                'customer_name' => $checkoutData['name'],
                'customer_email' => $checkoutData['email'],
                'customer_phone' => $checkoutData['phone'],
                'payment_methods' => [$request->payment_method],
                'success_redirect_url' => route('landing.payment.success', ['subscription_id' => $landingSubscription->id]),
                'failure_redirect_url' => route('landing.payment.failed', ['subscription_id' => $landingSubscription->id])
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