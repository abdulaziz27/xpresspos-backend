<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .header {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .header-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }
        .company-logo {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        .company-info {
            font-size: 11px;
            color: #666;
            line-height: 1.3;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .invoice-number {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .invoice-dates {
            font-size: 11px;
            color: #666;
        }
        .billing-section {
            display: table;
            width: 100%;
            margin: 30px 0;
        }
        .bill-to {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .payment-info {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-left: 30px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
        .customer-info {
            font-size: 12px;
            line-height: 1.4;
        }
        .customer-info strong {
            color: #333;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        .items-table th {
            background-color: #007bff;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
        }
        .items-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-section {
            width: 300px;
            margin-left: auto;
            margin-top: 20px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }
        .totals-table .total-label {
            font-weight: bold;
            text-align: right;
        }
        .totals-table .total-amount {
            text-align: right;
            font-weight: bold;
        }
        .grand-total {
            background-color: #007bff;
            color: white;
            font-size: 14px;
            font-weight: bold;
        }
        .payment-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status-failed {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        .notes-section {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .notes-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }
        .notes-content {
            font-size: 11px;
            color: #666;
            line-height: 1.4;
        }
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(220, 53, 69, 0.1);
            font-weight: bold;
            z-index: -1;
        }
    </style>
</head>
<body>
    @if($subscriptionPayment->status === 'failed')
    <div class="watermark">FAILED</div>
    @elseif($subscriptionPayment->status === 'pending')
    <div class="watermark">PENDING</div>
    @endif

    <div class="invoice-container">
        <!-- Header Section -->
        <div class="header">
            <div class="header-left">
                <div class="company-logo">{{ $companyInfo['name'] }}</div>
                <div class="company-info">
                    {{ $companyInfo['address'] }}<br>
                    {{ $companyInfo['city'] }}, {{ $companyInfo['country'] }}<br>
                    Phone: {{ $companyInfo['phone'] }}<br>
                    Email: {{ $companyInfo['email'] }}<br>
                    Website: {{ $companyInfo['website'] }}<br>
                    {{ $companyInfo['tax_id'] }}
                </div>
            </div>
            <div class="header-right">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number"># {{ $invoice->invoice_number }}</div>
                <div class="invoice-dates">
                    <strong>Issue Date:</strong> {{ $invoice->issued_at->format('M j, Y') }}<br>
                    <strong>Due Date:</strong> {{ $invoice->due_at->format('M j, Y') }}<br>
                    @if($invoice->paid_at)
                    <strong>Paid Date:</strong> {{ $invoice->paid_at->format('M j, Y') }}<br>
                    @endif
                </div>
            </div>
        </div>

        <!-- Billing Information -->
        <div class="billing-section">
            <div class="bill-to">
                <div class="section-title">Bill To</div>
                <div class="customer-info">
                    <strong>{{ $customerName }}</strong><br>
                    @if($customerAddress['company'])
                        {{ $customerAddress['company'] }}<br>
                    @endif
                    @if($customerAddress['address'])
                        {{ $customerAddress['address'] }}<br>
                    @endif
                    @if($customerAddress['city'])
                        {{ $customerAddress['city'] }}<br>
                    @endif
                    @if($customerAddress['country'])
                        {{ $customerAddress['country'] }}<br>
                    @endif
                    @if($customerEmail)
                        Email: {{ $customerEmail }}<br>
                    @endif
                    @if($customerAddress['phone'])
                        Phone: {{ $customerAddress['phone'] }}
                    @endif
                </div>
            </div>
            <div class="payment-info">
                <div class="section-title">Payment Information</div>
                <div class="customer-info">
                    <strong>Payment Status:</strong> 
                    <span class="payment-status status-{{ $subscriptionPayment->status }}">
                        {{ ucfirst($subscriptionPayment->status) }}
                    </span><br>
                    <strong>Payment Method:</strong> {{ $subscriptionPayment->getPaymentMethodDisplayName() }}<br>
                    <strong>Transaction ID:</strong> {{ $subscriptionPayment->xendit_invoice_id }}<br>
                    @if($subscriptionPayment->paid_at)
                    <strong>Payment Date:</strong> {{ $subscriptionPayment->paid_at->format('M j, Y g:i A') }}<br>
                    @endif
                    @if($subscriptionPayment->expires_at && $subscriptionPayment->status === 'pending')
                    <strong>Expires:</strong> {{ $subscriptionPayment->expires_at->format('M j, Y g:i A') }}
                    @endif
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-center">Period</th>
                    <th class="text-center">Quantity</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $planName }}</strong><br>
                        <small>XpressPOS Subscription Service</small>
                        @if($subscriptionPayment->subscription)
                            <br><small>Period: {{ $subscriptionPayment->subscription->starts_at->format('M j, Y') }} - {{ $subscriptionPayment->subscription->ends_at->format('M j, Y') }}</small>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($subscriptionPayment->subscription)
                            {{ ucfirst($subscriptionPayment->subscription->billing_cycle) }}
                        @else
                            Monthly
                        @endif
                    </td>
                    <td class="text-center">1</td>
                    <td class="text-right">Rp {{ number_format($subscriptionPayment->amount, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($subscriptionPayment->amount, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="total-label">Subtotal:</td>
                    <td class="total-amount">Rp {{ number_format($subscriptionPayment->amount, 0, ',', '.') }}</td>
                </tr>
                @if($subscriptionPayment->gateway_fee > 0)
                <tr>
                    <td class="total-label">Gateway Fee:</td>
                    <td class="total-amount">Rp {{ number_format($subscriptionPayment->gateway_fee, 0, ',', '.') }}</td>
                </tr>
                @endif
                <tr>
                    <td class="total-label">Tax (0%):</td>
                    <td class="total-amount">Rp 0</td>
                </tr>
                <tr class="grand-total">
                    <td class="total-label">Total Amount:</td>
                    <td class="total-amount">Rp {{ number_format($subscriptionPayment->amount, 0, ',', '.') }}</td>
                </tr>
            </table>
        </div>

        <!-- Notes Section -->
        <div class="notes-section">
            <div class="notes-title">Payment Terms & Notes</div>
            <div class="notes-content">
                @if($subscriptionPayment->isPaid())
                    Thank you for your payment! Your XpressPOS subscription is now active and ready to use.
                    This invoice serves as your official receipt for accounting purposes.
                @elseif($subscriptionPayment->status === 'pending')
                    This invoice is pending payment. Please complete your payment before the due date to activate your subscription.
                    You can pay online using the payment link provided in your email.
                @elseif($subscriptionPayment->hasFailed())
                    This payment has failed. Please contact our billing support team to resolve the issue and reactivate your subscription.
                @endif
                <br><br>
                <strong>Subscription Benefits:</strong><br>
                • Complete POS system with inventory management<br>
                • Real-time sales analytics and reporting<br>
                • Multi-location support and team management<br>
                • 24/7 customer support and regular updates<br>
                • Secure cloud backup and data protection
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>
                This is a computer-generated invoice. No signature is required.<br>
                Generated on {{ $generatedAt->format('M j, Y \a\t g:i A T') }} | 
                Invoice ID: {{ $invoice->id }} | 
                Payment ID: {{ $subscriptionPayment->id }}
            </p>
            <p>
                For billing inquiries, contact {{ $companyInfo['email'] }} or {{ $companyInfo['phone'] }}<br>
                © {{ date('Y') }} {{ $companyInfo['name'] }}. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>