<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Report - {{ $storeName }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #007bff;
            font-size: 24px;
        }
        .header h2 {
            margin: 5px 0;
            color: #666;
            font-size: 16px;
        }
        .summary-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .summary-row {
            display: table-row;
        }
        .summary-cell {
            display: table-cell;
            width: 50%;
            padding: 10px;
            vertical-align: top;
        }
        .metric-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #007bff;
        }
        .metric-label {
            font-weight: bold;
            color: #666;
            font-size: 11px;
            text-transform: uppercase;
        }
        .metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-top: 5px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 15px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $storeName }}</h1>
        <h2>Sales Report</h2>
        <p>{{ $data['period']['start_date'] }} to {{ $data['period']['end_date'] }}</p>
        <p>Generated on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
    </div>

    <!-- Summary Metrics -->
    <div class="summary-grid">
        <div class="summary-row">
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Total Orders</div>
                    <div class="metric-value">{{ number_format($data['summary']['total_orders']) }}</div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-value">${{ number_format($data['summary']['total_revenue'], 2) }}</div>
                </div>
            </div>
        </div>
        <div class="summary-row">
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Total Items</div>
                    <div class="metric-value">{{ number_format($data['summary']['total_items']) }}</div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Average Order Value</div>
                    <div class="metric-value">${{ number_format($data['summary']['average_order_value'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Timeline -->
    @if(!empty($data['timeline']))
    <div class="section">
        <div class="section-title">Sales Timeline</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-right">Orders</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Items</th>
                    <th class="text-right">Customers</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['timeline'] as $date => $timeline)
                <tr>
                    <td>{{ $date }}</td>
                    <td class="text-right">{{ number_format($timeline['orders']) }}</td>
                    <td class="text-right">${{ number_format($timeline['revenue'], 2) }}</td>
                    <td class="text-right">{{ number_format($timeline['items']) }}</td>
                    <td class="text-right">{{ number_format($timeline['customers']) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Payment Methods -->
    @if(!empty($data['payment_methods']))
    <div class="section">
        <div class="section-title">Payment Methods Breakdown</div>
        <table>
            <thead>
                <tr>
                    <th>Payment Method</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['payment_methods'] as $method => $methodData)
                <tr>
                    <td>{{ ucfirst($method) }}</td>
                    <td class="text-right">{{ number_format($methodData['count']) }}</td>
                    <td class="text-right">${{ number_format($methodData['amount'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Top Products -->
    @if(!empty($data['top_products']))
    <div class="section">
        <div class="section-title">Top Selling Products</div>
        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th class="text-right">Quantity</th>
                    <th class="text-right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['top_products'] as $product)
                <tr>
                    <td>{{ $product['name'] }}</td>
                    <td class="text-right">{{ number_format($product['quantity']) }}</td>
                    <td class="text-right">${{ number_format($product['revenue'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <p>This report was generated automatically by POS Xpress on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
        <p>© {{ date('Y') }} POS Xpress. All rights reserved.</p>
    </div>
</body>
</html>