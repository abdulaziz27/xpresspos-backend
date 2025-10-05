<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cash Flow Report - {{ $storeName }}</title>
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
            border-bottom: 2px solid #28a745;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #28a745;
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
            width: 33.33%;
            padding: 10px;
            vertical-align: top;
        }
        .metric-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #28a745;
            text-align: center;
        }
        .metric-box.revenue {
            border-left-color: #28a745;
        }
        .metric-box.expenses {
            border-left-color: #dc3545;
        }
        .metric-box.net-flow {
            border-left-color: #007bff;
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
        .metric-value.positive {
            color: #28a745;
        }
        .metric-value.negative {
            color: #dc3545;
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
            font-size: 10px;
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
        .daily-flow-table {
            font-size: 9px;
        }
        .daily-flow-table th,
        .daily-flow-table td {
            padding: 4px;
        }
        .cash-flow-positive {
            color: #28a745;
            font-weight: bold;
        }
        .cash-flow-negative {
            color: #dc3545;
            font-weight: bold;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $storeName }}</h1>
        <h2>Cash Flow Report</h2>
        <p>{{ $data['period']['start_date'] }} to {{ $data['period']['end_date'] }}</p>
        <p>Generated on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
    </div>

    <!-- Summary Metrics -->
    <div class="summary-grid">
        <div class="summary-row">
            <div class="summary-cell">
                <div class="metric-box revenue">
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-value positive">${{ number_format($data['summary']['total_revenue'], 2) }}</div>
                    <div style="font-size: 10px; color: #666; margin-top: 5px;">
                        {{ number_format($data['summary']['transaction_count']) }} transactions
                    </div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box expenses">
                    <div class="metric-label">Total Expenses</div>
                    <div class="metric-value negative">${{ number_format($data['summary']['total_expenses'], 2) }}</div>
                    <div style="font-size: 10px; color: #666; margin-top: 5px;">
                        {{ number_format($data['summary']['expense_count']) }} expenses
                    </div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box net-flow">
                    <div class="metric-label">Net Cash Flow</div>
                    <div class="metric-value {{ $data['summary']['net_cash_flow'] >= 0 ? 'positive' : 'negative' }}">
                        ${{ number_format($data['summary']['net_cash_flow'], 2) }}
                    </div>
                    <div style="font-size: 10px; color: #666; margin-top: 5px;">
                        Avg: ${{ number_format($data['summary']['average_transaction'], 2) }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Methods Breakdown -->
    @if(!empty($data['payment_methods']))
    <div class="section">
        <div class="section-title">💳 Payment Methods Breakdown</div>

        <table>
            <thead>
                <tr>
                    <th>Payment Method</th>
                    <th class="text-right">Transaction Count</th>
                    <th class="text-right">Total Amount</th>
                    <th class="text-right">Average Amount</th>
                    <th class="text-right">Percentage</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['payment_methods'] as $method => $methodData)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $method)) }}</td>
                    <td class="text-right">{{ number_format($methodData['count']) }}</td>
                    <td class="text-right">${{ number_format($methodData['total_amount'], 2) }}</td>
                    <td class="text-right">${{ number_format($methodData['average_amount'], 2) }}</td>
                    <td class="text-right">
                        {{ $data['summary']['total_revenue'] > 0 ? number_format(($methodData['total_amount'] / $data['summary']['total_revenue']) * 100, 1) : 0 }}%
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Expense Categories -->
    @if(!empty($data['expense_categories']))
    <div class="section">
        <div class="section-title">📊 Expense Categories</div>

        <table>
            <thead>
                <tr>
                    <th>Expense Category</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Total Amount</th>
                    <th class="text-right">Average Amount</th>
                    <th class="text-right">Percentage</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['expense_categories'] as $category => $categoryData)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $category)) }}</td>
                    <td class="text-right">{{ number_format($categoryData['count']) }}</td>
                    <td class="text-right">${{ number_format($categoryData['total_amount'], 2) }}</td>
                    <td class="text-right">${{ number_format($categoryData['average_amount'], 2) }}</td>
                    <td class="text-right">
                        {{ $data['summary']['total_expenses'] > 0 ? number_format(($categoryData['total_amount'] / $data['summary']['total_expenses']) * 100, 1) : 0 }}%
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Daily Cash Flow -->
    @if(!empty($data['daily_flow']))
    <div class="section page-break">
        <div class="section-title">📅 Daily Cash Flow Analysis</div>

        <table class="daily-flow-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Expenses</th>
                    <th class="text-right">Net Flow</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['daily_flow'] as $date => $flow)
                <tr>
                    <td>{{ $date }}</td>
                    <td class="text-right">${{ number_format($flow['revenue'], 2) }}</td>
                    <td class="text-right">${{ number_format($flow['expenses'], 2) }}</td>
                    <td class="text-right {{ $flow['net_flow'] >= 0 ? 'cash-flow-positive' : 'cash-flow-negative' }}">
                        ${{ number_format($flow['net_flow'], 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Cash Sessions (if included) -->
    @if(!empty($data['cash_sessions']))
    <div class="section">
        <div class="section-title">💰 Cash Sessions Summary</div>

        <table>
            <thead>
                <tr>
                    <th>Session ID</th>
                    <th>Cashier</th>
                    <th class="text-right">Opening Balance</th>
                    <th class="text-right">Closing Balance</th>
                    <th class="text-right">Expected Balance</th>
                    <th class="text-right">Variance</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['cash_sessions'] as $session)
                <tr>
                    <td>{{ $session['id'] }}</td>
                    <td>{{ $session['user'] }}</td>
                    <td class="text-right">${{ number_format($session['opening_balance'], 2) }}</td>
                    <td class="text-right">${{ number_format($session['closing_balance'], 2) }}</td>
                    <td class="text-right">${{ number_format($session['expected_balance'], 2) }}</td>
                    <td class="text-right {{ $session['variance'] == 0 ? 'cash-flow-positive' : 'cash-flow-negative' }}">
                        ${{ number_format($session['variance'], 2) }}
                    </td>
                    <td class="text-center">
                        <span style="padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; text-transform: uppercase;
                            background-color: {{ $session['status'] === 'closed' ? '#d4edda' : '#fff3cd' }};
                            color: {{ $session['status'] === 'closed' ? '#155724' : '#856404' }};">
                            {{ $session['status'] }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Cash Flow Summary -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 30px 0;">
        <h4 style="margin-top: 0; color: #495057;">💡 Cash Flow Insights</h4>
        <ul style="color: #495057; margin: 10px 0; padding-left: 20px;">
            <li><strong>Total Cash In:</strong> ${{ number_format($data['summary']['total_revenue'], 2) }} from {{ number_format($data['summary']['transaction_count']) }} transactions</li>
            <li><strong>Total Cash Out:</strong> ${{ number_format($data['summary']['total_expenses'], 2) }} across {{ number_format($data['summary']['expense_count']) }} expenses</li>
            <li><strong>Net Cash Flow:</strong>
                <span class="{{ $data['summary']['net_cash_flow'] >= 0 ? 'cash-flow-positive' : 'cash-flow-negative' }}">
                    ${{ number_format($data['summary']['net_cash_flow'], 2) }}
                </span>
            </li>
            <li><strong>Average Transaction:</strong> ${{ number_format($data['summary']['average_transaction'], 2) }}</li>
            <li><strong>Average Expense:</strong> ${{ number_format($data['summary']['average_expense'], 2) }}</li>
        </ul>
    </div>

    <div class="footer">
        <p>This cash flow report was generated automatically by POS Xpress on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
        <p>© {{ date('Y') }} POS Xpress. All rights reserved.</p>
    </div>
</body>
</html>
