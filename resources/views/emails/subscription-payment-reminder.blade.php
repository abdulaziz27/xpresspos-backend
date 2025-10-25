<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Reminder - {{ $planName }}</title>
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
            border-bottom: 3px solid {{ $isUrgent ? '#dc3545' : '#ffc107' }};
        }
        .header h1 {
            color: {{ $isUrgent ? '#dc3545' : '#856404' }};
            margin: 0;
            font-size: 28px;
        }
        .header .reminder-icon {
            font-size: 48px;
            color: {{ $isUrgent ? '#dc3545' : '#ffc107' }};
            margin-bottom: 10px;
        }
        .reminder-summary {
            background: {{ $isUrgent ? '#f8d7da' : '#fff3cd' }};
            border: 1px solid {{ $isUrgent ? '#f5c6cb' : '#ffeaa7' }};
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }
        .reminder-summary h2 {
            color: {{ $isUrgent ? '#721c24' : '#856404' }};
            margin: 0 0 15px 0;
            font-size: 24px;
        }
        .days-remaining {
            font-size: 48px;
            font-weight: bold;
            color: {{ $isUrgent ? '#721c24' : '#856404' }};
            margin: 10px 0;
        }
        .plan-name {
            font-size: 18px;
            color: {{ $isUrgent ? '#721c24' : '#856404' }};
            margin-bottom: 10px;
        }
        .subscription-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .subscription-details h3 {
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
        .renewal-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .renewal-info h3 {
            margin-top: 0;
            color: #0056b3;
        }
        .renewal-amount {
            font-size: 24px;
            font-weight: bold;
            color: #0056b3;
            margin: 10px 0;
        }
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        .cta-button {
            display: inline-block;
            background: {{ $isUrgent ? '#dc3545' : '#007bff' }};
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 10px;
            text-align: center;
        }
        .cta-button:hover {
            background: {{ $isUrgent ? '#c82333' : '#0056b3' }};
        }
        .cta-button.secondary {
            background: #6c757d;
        }
        .cta-button.secondary:hover {
            background: #545b62;
        }
        .benefits-reminder {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .benefits-reminder h3 {
            margin-top: 0;
            color: #155724;
        }
        .benefit-item {
            color: #155724;
            margin: 8px 0;
            padding-left: 20px;
            position: relative;
        }
        .benefit-item:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #28a745;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .urgency-notice {
            background: {{ $isUrgent ? '#f8d7da' : '#d1ecf1' }};
            border: 1px solid {{ $isUrgent ? '#f5c6cb' : '#bee5eb' }};
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: {{ $isUrgent ? '#721c24' : '#0c5460' }};
        }
        .urgency-notice strong {
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
        .countdown-visual {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 20px 0;
        }
        .countdown-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: {{ $isUrgent ? '#dc3545' : '#ffc107' }};
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="reminder-icon">{{ $isUrgent ? '🚨' : '⏰' }}</div>
            <h1>{{ $isUrgent ? 'Urgent: ' : '' }}Payment Reminder</h1>
            <p>Your XpressPOS subscription expires soon</p>
        </div>

        <div class="reminder-summary">
            <h2>{{ $isUrgent ? 'Action Required!' : 'Renewal Coming Up' }}</h2>
            <div class="countdown-visual">
                <div class="countdown-circle">{{ $daysUntilExpiration }}</div>
                <div style="color: {{ $isUrgent ? '#721c24' : '#856404' }}; font-weight: bold;">
                    {{ $daysUntilExpiration === 1 ? 'Day' : 'Days' }}<br>Remaining
                </div>
            </div>
            <div class="plan-name">{{ $planName }}</div>
            @if($isUrgent)
                <p><strong>Your subscription expires very soon. Renew now to avoid service interruption.</strong></p>
            @else
                <p>Your subscription will renew automatically unless you take action.</p>
            @endif
        </div>

        <div class="subscription-details">
            <h3>📋 Subscription Details</h3>
            
            <div class="detail-row">
                <span class="detail-label">Store Name:</span>
                <span class="detail-value">{{ $storeName }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">{{ $storeEmail }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Current Plan:</span>
                <span class="detail-value">{{ $planName }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Billing Cycle:</span>
                <span class="detail-value">{{ ucfirst($billingCycle) }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Current Period Ends:</span>
                <span class="detail-value">{{ $currentPeriodEnd->format('M j, Y') }}</span>
            </div>
        </div>

        <div class="renewal-info">
            <h3>💳 Upcoming Renewal</h3>
            <div class="renewal-amount">Rp {{ number_format($renewalAmount, 0, ',', '.') }}</div>
            <p>
                Your subscription will automatically renew for another {{ $billingCycle }} period 
                on {{ $currentPeriodEnd->format('M j, Y') }}.
            </p>
            @if($upcomingPayment)
                <p><small>Payment will be processed using your saved payment method.</small></p>
            @endif
        </div>

        @if($isUrgent)
        <div class="urgency-notice">
            <strong>⚠️ Service Interruption Warning</strong>
            Your subscription expires in {{ $daysUntilExpiration }} {{ $daysUntilExpiration === 1 ? 'day' : 'days' }}. 
            If payment is not processed by {{ $currentPeriodEnd->format('M j, Y \a\t 11:59 PM') }}, 
            your XpressPOS service will be suspended.
        </div>
        @else
        <div class="urgency-notice">
            <strong>📅 Renewal Reminder</strong>
            This is a friendly reminder that your subscription will renew in {{ $daysUntilExpiration }} days. 
            No action is required unless you want to make changes to your subscription.
        </div>
        @endif

        <div class="action-buttons">
            @if($isUrgent)
                <a href="{{ config('app.url') }}/subscription/renew" class="cta-button">
                    🚀 Renew Now
                </a>
                <br>
                <a href="{{ config('app.url') }}/subscription/payment-methods" class="cta-button secondary">
                    💳 Update Payment Method
                </a>
            @else
                <a href="{{ config('app.url') }}/subscription/manage" class="cta-button">
                    📊 Manage Subscription
                </a>
                <br>
                <a href="{{ config('app.url') }}/subscription/payment-methods" class="cta-button secondary">
                    💳 Update Payment Method
                </a>
            @endif
        </div>

        <div class="benefits-reminder">
            <h3>🎯 What You'll Keep With {{ $planName }}</h3>
            <div class="benefit-item">Complete POS system with inventory management</div>
            <div class="benefit-item">Real-time sales analytics and reporting</div>
            <div class="benefit-item">Multi-location support and team management</div>
            <div class="benefit-item">24/7 customer support and updates</div>
            <div class="benefit-item">Secure cloud backup and data protection</div>
        </div>

        @if(!$isUrgent)
        <div style="background: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #495057;">🔄 Automatic Renewal</h3>
            <p style="color: #6c757d; margin-bottom: 0;">
                Your subscription is set to renew automatically. You can cancel or modify your subscription 
                at any time before the renewal date. Changes will take effect at the end of your current billing period.
            </p>
        </div>
        @endif

        <div class="support-info">
            <strong>📞 Need Help?</strong><br>
            Questions about your subscription or billing? We're here to help:<br>
            • Email: billing@xpresspos.com<br>
            • Phone: +62-XXX-XXXX-XXXX<br>
            • Live Chat: Available in your dashboard
        </div>

        <div class="footer">
            <p>This is an automated reminder from XpressPOS</p>
            <p>Subscription ID: {{ $subscription->id }}</p>
            <p>© {{ date('Y') }} XpressPOS. All rights reserved.</p>
        </div>
    </div>
</body>
</html>