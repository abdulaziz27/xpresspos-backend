<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation - {{ $planName }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #28a745;
        }
        .header h1 {
            color: #28a745;
            margin: 0;
            font-size: 28px;
        }
        .header .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 10px;
        }
        .payment-summary {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }
        .payment-summary h2 {
            color: #155724;
            margin: 0 0 15px 0;
            font-size: 24px;
        }
        .amount {
            font-size: 36px;
            font-weight: bold;
            color: #155724;
            margin: 10px 0;
        }
        .plan-name {
            font-size: 18px;
            color: #155724;
            margin-bottom: 10px;
        }
        .payment-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .payment-details h3 {
            margin-top: 0;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #495057;
        }
        .detail-value {
            color: #6c757d;
            text-align: right;
        }
        .subscription-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .subscription-info h3 {
            margin-top: 0;
            color: #0056b3;
        }
        .subscription-period {
            font-size: 16px;
            color: #0056b3;
            margin: 10px 0;
        }
        .next-steps {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .next-steps h3 {
            margin-top: 0;
            color: #856404;
        }
        .step-item {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 3px solid #ffc107;
        }
        .step-number {
            display: inline-block;
            background: #ffc107;
            color: #856404;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            text-align: center;
            line-height: 25px;
            font-weight: bold;
            margin-right: 10px;
        }
        .cta-button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
        }
        .cta-button:hover {
            background: #0056b3;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .attachment-notice {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #0c5460;
        }
        .attachment-notice strong {
            display: block;
            margin-bottom: 5px;
        }
        .support-info {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">✅</div>
            <h1>Payment Confirmed!</h1>
            <p>Thank you for your subscription to XpressPOS</p>
        </div>

        <div class="payment-summary">
            <h2>Payment Successful</h2>
            <div class="amount">Rp {{ number_format($subscriptionPayment->amount, 0, ',', '.') }}</div>
            <div class="plan-name">{{ $planName }}</div>
            <p>Your subscription is now active and ready to use!</p>
        </div>

        <div class="payment-details">
            <h3>📋 Payment Details</h3>
            
            <div class="detail-row">
                <span class="detail-label">Customer Name:</span>
                <span class="detail-value">{{ $customerName }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">{{ $customerEmail }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Plan:</span>
                <span class="detail-value">{{ $planName }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Amount Paid:</span>
                <span class="detail-value">Rp {{ number_format($subscriptionPayment->amount, 0, ',', '.') }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value">{{ $paymentMethod }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value">{{ $transactionId }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Date:</span>
                <span class="detail-value">{{ $paidAt->format('M j, Y \a\t g:i A') }}</span>
            </div>
        </div>

        @if($subscriptionStartDate && $subscriptionEndDate)
        <div class="subscription-info">
            <h3>📅 Subscription Period</h3>
            <div class="subscription-period">
                <strong>Active from:</strong> {{ $subscriptionStartDate->format('M j, Y') }}<br>
                <strong>Valid until:</strong> {{ $subscriptionEndDate->format('M j, Y') }}
            </div>
            <p>Your subscription will automatically renew unless cancelled before the end date.</p>
        </div>
        @endif

        @if($hasInvoicePdf)
        <div class="attachment-notice">
            <strong>📄 Invoice Attached</strong>
            Your official invoice has been attached to this email as a PDF document. Please keep this for your records and accounting purposes.
        </div>
        @endif

        <div class="next-steps">
            <h3>🚀 What's Next?</h3>
            
            <div class="step-item">
                <span class="step-number">1</span>
                <strong>Access Your Dashboard:</strong> Log in to your XpressPOS dashboard to start setting up your store and managing your business.
            </div>
            
            <div class="step-item">
                <span class="step-number">2</span>
                <strong>Complete Setup:</strong> Follow our onboarding guide to configure your products, payment methods, and store settings.
            </div>
            
            <div class="step-item">
                <span class="step-number">3</span>
                <strong>Start Selling:</strong> Begin processing orders and managing your business with XpressPOS's powerful features.
            </div>
        </div>

        <div style="text-align: center;">
            <a href="{{ config('app.url') }}" class="cta-button">🏪 Access Your Dashboard</a>
        </div>

        <div class="support-info">
            <strong>📞 Need Help?</strong><br>
            Our support team is here to help you get started:<br>
            • Email: support@xpresspos.com<br>
            • Documentation: {{ config('app.url') }}/docs<br>
            • Live Chat: Available in your dashboard
        </div>

        <div class="footer">
            <p>This is an automated confirmation email from XpressPOS</p>
            <p>Transaction processed on {{ $paidAt->format('M j, Y \a\t g:i A T') }}</p>
            <p>© {{ date('Y') }} XpressPOS. All rights reserved.</p>
        </div>
    </div>
</body>
</html>