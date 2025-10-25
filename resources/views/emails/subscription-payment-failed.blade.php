<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - {{ $planName }}</title>
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
        .header .error-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 10px;
        }
        .payment-summary {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }
        .payment-summary h2 {
            color: #721c24;
            margin: 0 0 15px 0;
            font-size: 24px;
        }
        .amount {
            font-size: 36px;
            font-weight: bold;
            color: #721c24;
            margin: 10px 0;
        }
        .plan-name {
            font-size: 18px;
            color: #721c24;
            margin-bottom: 10px;
        }
        .failure-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .failure-details h3 {
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
        .retry-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .retry-info h3 {
            margin-top: 0;
            color: #856404;
        }
        .retry-countdown {
            font-size: 16px;
            color: #856404;
            margin: 10px 0;
        }
        .action-required {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .action-required h3 {
            margin-top: 0;
            color: #0c5460;
        }
        .action-item {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 3px solid #17a2b8;
        }
        .action-number {
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
        .warning-notice {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #721c24;
        }
        .warning-notice strong {
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
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 8px;
            margin: 10px 0;
            overflow: hidden;
        }
        .progress-fill {
            background: #dc3545;
            height: 100%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="error-icon">❌</div>
            <h1>Payment Failed</h1>
            <p>We couldn't process your subscription payment</p>
        </div>

        <div class="payment-summary">
            <h2>Payment Unsuccessful</h2>
            <div class="amount">Rp {{ number_format($subscriptionPayment->amount, 0, ',', '.') }}</div>
            <div class="plan-name">{{ $planName }}</div>
            @if($isRenewal)
                <p>Your subscription renewal payment could not be processed.</p>
            @else
                <p>Your new subscription payment could not be processed.</p>
            @endif
        </div>

        <div class="failure-details">
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
                <span class="detail-label">Amount:</span>
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
            
            @if($failureReason)
            <div class="detail-row">
                <span class="detail-label">Failure Reason:</span>
                <span class="detail-value">{{ $failureReason }}</span>
            </div>
            @endif
        </div>

        @if($retryCount < $maxRetries && $nextRetryDate)
        <div class="retry-info">
            <h3>🔄 Automatic Retry Scheduled</h3>
            <div class="retry-countdown">
                <strong>Next retry attempt:</strong> {{ $nextRetryDate->format('M j, Y \a\t g:i A') }}
            </div>
            <p>We'll automatically attempt to process your payment again in 24 hours. No action is required from you.</p>
            
            <div style="margin: 15px 0;">
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #856404;">
                    <span>Retry {{ $retryCount + 1 }} of {{ $maxRetries }}</span>
                    <span>{{ $maxRetries - $retryCount - 1 }} attempts remaining</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ (($retryCount + 1) / $maxRetries) * 100 }}%"></div>
                </div>
            </div>
        </div>
        @endif

        @if($expirationDate)
        <div class="warning-notice">
            <strong>⚠️ Payment Deadline</strong>
            @if($isRenewal)
                Your subscription will be suspended if payment is not received by {{ $expirationDate->format('M j, Y \a\t g:i A') }}.
            @else
                This payment link will expire on {{ $expirationDate->format('M j, Y \a\t g:i A') }}.
            @endif
        </div>
        @endif

        <div class="action-required">
            <h3>🚨 Action Required</h3>
            
            <div class="action-item">
                <span class="action-number">1</span>
                <strong>Check Payment Method:</strong> Ensure your payment method has sufficient funds and is not expired or blocked.
            </div>
            
            <div class="action-item">
                <span class="action-number">2</span>
                <strong>Update Payment Details:</strong> If needed, update your payment information in your account dashboard.
            </div>
            
            <div class="action-item">
                <span class="action-number">3</span>
                <strong>Retry Payment:</strong> You can manually retry the payment at any time before the deadline.
            </div>
        </div>

        <div style="text-align: center;">
            <a href="{{ config('app.url') }}/subscription/retry-payment/{{ $subscriptionPayment->id }}" class="cta-button">
                🔄 Retry Payment Now
            </a>
            <br>
            <a href="{{ config('app.url') }}/subscription/payment-methods" class="cta-button secondary">
                💳 Update Payment Method
            </a>
        </div>

        @if($isRenewal)
        <div style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #0056b3;">📞 Service Continuity</h3>
            <p style="color: #0056b3; margin-bottom: 0;">
                Your current subscription remains active during the retry period. 
                Your service will only be affected if all retry attempts fail and the payment deadline passes.
            </p>
        </div>
        @endif

        <div class="support-info">
            <strong>📞 Need Help?</strong><br>
            If you continue to experience payment issues, our support team is here to help:<br>
            • Email: billing@xpresspos.com<br>
            • Phone: +62-XXX-XXXX-XXXX<br>
            • Live Chat: Available in your dashboard
        </div>

        <div class="footer">
            <p>This is an automated notification from XpressPOS</p>
            <p>Payment attempt failed on {{ now()->format('M j, Y \a\t g:i A T') }}</p>
            <p>© {{ date('Y') }} XpressPOS. All rights reserved.</p>
        </div>
    </div>
</body>
</html>