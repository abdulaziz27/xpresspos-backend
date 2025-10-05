<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Analytics Report - {{ $storeName }}</title>
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
            border-bottom: 2px solid #17a2b8;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #17a2b8;
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
            width: 25%;
            padding: 10px;
            vertical-align: top;
        }
        .metric-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #17a2b8;
            text-align: center;
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
        .segment-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .segment-new {
            background-color: #d4edda;
            color: #155724;
        }
        .segment-returning {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .segment-vip {
            background-color: #fff3cd;
            color: #856404;
        }
        .page-break {
            page-break-before: always;
        }
        .insight-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .insight-box h4 {
            margin-top: 0;
            color: #0056b3;
        }
        .loyalty-stats {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $storeName }}</h1>
        <h2>Customer Analytics Report</h2>
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
                    <div class="metric-label">Unique Customers</div>
                    <div class="metric-value">{{ number_format($data['summary']['unique_customers']) }}</div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Member Orders</div>
                    <div class="metric-value">{{ number_format($data['summary']['member_percentage'], 1) }}%</div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Avg Order Value</div>
                    <div class="metric-value">${{ number_format($data['summary']['average_order_value'], 2) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Segmentation -->
    <div class="section">
        <div class="section-title">👥 Customer Segmentation</div>

        <table>
            <thead>
                <tr>
                    <th>Customer Segment</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Percentage</th>
                    <th class="text-right">Total Orders</th>
                    <th class="text-right">Avg Order Value</th>
                    <th class="text-right">Total Revenue</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Guest Customers</td>
                    <td class="text-right">{{ number_format($data['summary']['guest_orders']) }}</td>
                    <td class="text-right">{{ number_format((100 - $data['summary']['member_percentage']), 1) }}%</td>
                    <td class="text-right">{{ number_format($data['summary']['guest_orders']) }}</td>
                    <td class="text-right">${{ number_format($data['summary']['average_order_value'], 2) }}</td>
                    <td class="text-right">-</td>
                </tr>
                <tr>
                    <td>Member Customers</td>
                    <td class="text-right">{{ number_format($data['summary']['member_orders']) }}</td>
                    <td class="text-right">{{ number_format($data['summary']['member_percentage'], 1) }}%</td>
                    <td class="text-right">{{ number_format($data['summary']['member_orders']) }}</td>
                    <td class="text-right">${{ number_format($data['summary']['average_order_value'], 2) }}</td>
                    <td class="text-right">${{ number_format($data['summary']['total_revenue'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Top Customers -->
    @if(!empty($data['top_customers']))
    <div class="section">
        <div class="section-title">🏆 Top Customers</div>

        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Customer Name</th>
                    <th>Email</th>
                    <th class="text-right">Orders</th>
                    <th class="text-right">Total Spent</th>
                    <th class="text-right">Avg Order</th>
                    <th class="text-right">Last Order</th>
                    <th class="text-center">Segment</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['top_customers'] as $index => $customer)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->email }}</td>
                    <td class="text-right">{{ number_format($customer->order_count) }}</td>
                    <td class="text-right">${{ number_format($customer->total_spent, 2) }}</td>
                    <td class="text-right">${{ number_format($customer->average_order, 2) }}</td>
                    <td class="text-right">{{ \Carbon\Carbon::parse($customer->last_order_date)->format('M j, Y') }}</td>
                    <td class="text-center">
                        @php
                            $segment = 'new';
                            if ($customer->order_count >= 10 && $customer->total_spent >= 500) {
                                $segment = 'vip';
                            } elseif ($customer->order_count >= 3) {
                                $segment = 'returning';
                            }
                        @endphp
                        <span class="segment-badge segment-{{ $segment }}">
                            {{ $segment }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Customer Segments Analysis -->
    @if(!empty($data['segments']))
    <div class="section page-break">
        <div class="section-title">📊 Customer Segments Analysis</div>

        <table>
            <thead>
                <tr>
                    <th>Segment</th>
                    <th class="text-right">Customer Count</th>
                    <th class="text-right">Total Revenue</th>
                    <th class="text-right">Avg Revenue per Customer</th>
                    <th class="text-right">Avg Frequency</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['segments'] as $segment => $segmentData)
                <tr>
                    <td>{{ $segment }}</td>
                    <td class="text-right">{{ number_format($segmentData['count']) }}</td>
                    <td class="text-right">${{ number_format($segmentData['total_monetary'], 2) }}</td>
                    <td class="text-right">${{ number_format($segmentData['avg_monetary'], 2) }}</td>
                    <td class="text-right">{{ number_format($segmentData['avg_frequency'], 1) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Loyalty Statistics (if available) -->
    @if(!empty($data['loyalty_stats']))
    <div class="section">
        <div class="section-title">🎯 Loyalty Program Statistics</div>

        <div class="loyalty-stats">
            <div style="display: table; width: 100%;">
                <div style="display: table-row;">
                    <div style="display: table-cell; width: 33.33%; padding: 10px;">
                        <strong>Points Earned:</strong> {{ number_format($data['loyalty_stats']['points_earned']) }}
                    </div>
                    <div style="display: table-cell; width: 33.33%; padding: 10px;">
                        <strong>Points Redeemed:</strong> {{ number_format($data['loyalty_stats']['points_redeemed']) }}
                    </div>
                    <div style="display: table-cell; width: 33.33%; padding: 10px;">
                        <strong>Active Members:</strong> {{ number_format($data['loyalty_stats']['active_members']) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Customer Insights -->
    <div class="insight-box">
        <h4>💡 Customer Insights</h4>
        <ul style="color: #0056b3; margin: 10px 0; padding-left: 20px;">
            <li><strong>Customer Mix:</strong> {{ number_format($data['summary']['member_percentage'], 1) }}% of orders are from members, indicating {{ $data['summary']['member_percentage'] >= 50 ? 'strong' : 'moderate' }} loyalty program engagement</li>

            @if($data['summary']['unique_customers'] > 0)
            <li><strong>Order Frequency:</strong> Average of {{ number_format($data['summary']['total_orders'] / $data['summary']['unique_customers'], 1) }} orders per customer</li>
            @endif

            @if(!empty($data['top_customers']) && count($data['top_customers']) > 0)
                @php
                    $topCustomer = $data['top_customers'][0];
                    $vipCustomers = array_filter($data['top_customers'], function($c) { return $c->order_count >= 10 && $c->total_spent >= 500; });
                @endphp
            <li><strong>Top Customer:</strong> {{ $topCustomer->name }} with {{ $topCustomer->order_count }} orders and ${{ number_format($topCustomer->total_spent, 2) }} total spent</li>

            @if(count($vipCustomers) > 0)
            <li><strong>VIP Customers:</strong> {{ count($vipCustomers) }} customers qualify as VIP (10+ orders, $500+ spent)</li>
            @endif
            @endif

            <li><strong>Revenue Distribution:</strong> Total revenue of ${{ number_format($data['summary']['total_revenue'], 2) }} across {{ number_format($data['summary']['total_orders']) }} orders</li>
        </ul>
    </div>

    <!-- Recommendations -->
    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 20px 0;">
        <h4 style="margin-top: 0; color: #856404;">🎯 Strategic Recommendations</h4>

        <ul style="color: #856404; margin: 10px 0; padding-left: 20px;">
            @if($data['summary']['member_percentage'] < 50)
            <li><strong>Improve Member Conversion:</strong> Only {{ number_format($data['summary']['member_percentage'], 1) }}% of orders are from members. Focus on converting guest customers to members through incentives and loyalty programs.</li>
            @endif

            @if($data['summary']['unique_customers'] > 0 && ($data['summary']['total_orders'] / $data['summary']['unique_customers']) < 2)
            <li><strong>Increase Repeat Business:</strong> Average order frequency is {{ number_format($data['summary']['total_orders'] / $data['summary']['unique_customers'], 1) }} orders per customer. Implement retention strategies to encourage repeat visits.</li>
            @endif

            @if(!empty($data['top_customers']) && count($data['top_customers']) > 0)
            <li><strong>VIP Program:</strong> Focus on nurturing high-value customers identified in the top customers list. Consider creating exclusive offers for VIP customers.</li>
            @endif

            <li><strong>Customer Communication:</strong> Use customer data to create personalized marketing campaigns and improve customer engagement.</li>

            <li><strong>Feedback Collection:</strong> Implement systematic feedback collection to understand customer preferences and improve service quality.</li>
        </ul>
    </div>

    <div class="footer">
        <p>This customer analytics report was generated automatically by POS Xpress on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
        <p>© {{ date('Y') }} POS Xpress. All rights reserved.</p>
    </div>
</body>
</html>
