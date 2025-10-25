<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Suspended - {{ $planName }}</title>
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
            border-bottom: 3px solid #dc3545;
        }
        .header h1 {
            color: #dc3545;
            margin: 0;
            font-size: 28px;
        }
        .header .suspension-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 10px;
        }
        .suspension-summary {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }
        .suspension-summary h2 {
            color: #721c24;
            margin: 0 0 15px 0;
            font-size: 24px;
        }
        .suspension-date {
            font-size: 18px;
            font-weight: bold;
            color: #721c24;
            margin: 10px 0;
        }
        .plan-name {
            font-size: 18px;
            color: #721c24;
            margin-bottom: 10px;
        }
        .suspension-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .suspension-details h3 {
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
        .grace-period {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .grace-period h3 {
            margin-top: 0;
            color: #856404;
        }
        .grace-countdown {
            font-size: 24px;
            font-weight: bold;
            color: #856404;
            margin: 10px 0;
            text-align: center;
        }
        .reactivation-steps {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .reactivation-steps h3 {
            margin-top: 0;
            color: #0c5460;
        }
        .step-item {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 3px solid #17a2b8;
        }
        .step-number {
            display: inline-block;
            background: #17a2b8;
            color: white;
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
            background: #dc3545;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
        }
        .cta-button:hover {
            background: #c82333;
        }
        .cta-button.secondary {
            background: #007bff;
        }
        .cta-button.secondary:hover {
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
        .impact-notice {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #721c24;
        }
        .impact-notice strong {
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
        .data-retention {
            background: #e2e3e5;
            border: 1px solid #d6d8db;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .data-retention h3 {
            margin-top: 0;
            color: #383d41;
        }
        .outstanding-payment {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .outstanding-amount {
            font-size: 32px;
            font-weight: bold;
            color: #856404;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="suspension-icon">🚫</div>
            <h1>Service Suspended</h1>
            <p>Your XpressPOS subscription has been suspended</p>
        </div>

        <div class="suspension-summary">
            <h2>Subscription Suspended</h2>
            <div class="suspension-date">{{ $suspensionDate->format('M j, Y \a\t g:i A') }}</div>
            <div class="plan-name">{{ $planName }}</div>
            <p><strong>Reason:</strong> {{ $suspensionReason }}</p>
        </div>

        <div class="impact-notice">
            <strong>⚠️ Service Impact</strong>
            Your XpressPOS system is currently suspended. You cannot process new orders, access reports, 
            or use any POS features until your subscription is reactivated.
        </div>

        <div class="suspension-details">
            <h3>📋 Suspension Details</h3>
            
            <div class="detail-row">
                <span class="detail-label">Store Name:</span>
                <span class="detail-value">{{ $storeName }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">{{ $storeEmail }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Suspended Plan:</span>
                <span class="detail-value">{{ $planName }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Billing Cycle:</span>
                <span class="detail-value">{{ ucfirst($billingCycle) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Suspension Date:</span>
                <span class="detail-value">{{ $suspensionDate->format('M j, Y \a\t g:i A') }}</span>
            </div>
            
            @if($failedPayment)
            <div class="detail-row">
                <span class="detail-label">Failed Payment ID:</span>
                <span class="detail-value">{{ $failedPayment->xendit_invoice_id }}</span>
            </div>
            @endif
        </div>

        <div class="outstanding-payment">
            <h3 style="margin-top: 0; color: #856404;">💳 Outstanding Payment</h3>
            <div class="outstanding-amount">Rp {{ number_format($outstandingAmount, 0, ',', '.') }}</div>
            <p style="color: #856404; margin-bottom: 0;">
                Pay this amount to immediately reactivate your subscription
            </p>
        </div>

        <div class="grace-period">
            <h3>⏳ Grace Period Active</h3>
            <div class="grace-countdown">{{ $gracePeriodDays }} Days Remaining</div>
            <p>
                You have until <strong>{{ $gracePeriodEnd->format('M j, Y \a\t 11:59 PM') }}</strong> 
                to reactivate your subscription. After this grace period, your account and data may be permanently deleted.
            </p>
        </div>

        <div class="reactivation-steps">
            <h3>🔄 How to Reactivate Your Service</h3>
            
            <div class="step-item">
                <span class="step-number">1</span>
                <strong>Make Payment:</strong> Pay the outstanding amount to immediately restore your service.
            </div>
            
            <div class="step-item">
                <span class="step-number">2</span>
                <strong>Update Payment Method:</strong> Ensure your payment method is valid to prevent future suspensions.
            </div>
            
            <div class="step-item">
                <span class="step-number">3</span>
                <strong>Verify Reactivation:</strong> Your service will be restored within minutes of successful payment.
            </div>
        </div>

        <div style="text-align: center;">
            <a href="{{ config('app.url') }}/subscription/reactivate" class="cta-button">
                🚀 Reactivate Now
            </a>
            <br>
            <a href="{{ config('app.url') }}/subscription/payment-methods" class="cta-button secondary">
                💳 Update Payment Method
            </a>
        </div>

        <div class="data-retention">
            <h3>💾 Your Data is Safe</h3>
            <p style="color: #383d41; margin-bottom: 0;">
                All your business data, including products, orders, and customer information, 
                is securely stored and will be fully restored when you reactivate your subscription. 
                Data is retained for {{ $gracePeriodDays }} days after suspension.
            </p>
        </div>

        <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #155724;">🔒 Account Security</h3>
            <p style="color: #155724; margin-bottom: 0;">
                Your account remains secure during suspension. No unauthorized access is possible, 
                and all your settings and configurations are preserved for when you return.
            </p>
        </div>

        <div class="support-info">
            <strong>📞 Need Immediate Help?</strong><br>
            Our billing support team is standing by to help you reactivate your service:<br>
            • Email: billing@xpresspos.com<br>
            • Phone: +62-XXX-XXXX-XXXX (24/7 Billing Support)<br>
            • Emergency Reactivation: Available via phone
        </div>

        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px; padding: 15px; margin: 20px 0; color: #856404;">
            <strong>⚡ Quick Reactivation Available</strong><br>
            Pay now and your service will be restored immediately. 
            Don't let a payment issue disrupt your business operations.
        </div>

        <div class="footer">
            <p>This is an automated notification from XpressPOS</p>
            <p>Subscription ID: {{ $subscription->id }}</p>
            <p>Suspended on {{ $suspensionDate->format('M j, Y \a\t g:i A T') }}</p>
            <p>© {{ date('Y') }} XpressPOS. All rights reserved.</p>
        </div>
    </div>
</body>
</html>