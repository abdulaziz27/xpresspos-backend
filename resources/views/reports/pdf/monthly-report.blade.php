<!DOCT
YPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Business Report - {{ $store->name }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #007bff;
        }
        .header h1 {
            margin: 0;
            color: #007bff;
            font-size: 28px;
        }
        .header h2 {
            margin: 10px 0 5px 0;
            color: #666;
            font-size: 18px;
        }
        .header p {
            margin: 5px 0;
            color: #888;
        }
        .executive-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .summary-title {
            font-size: 18px;
            font-weight: bold;
            color: #495057;
            margin-bottom: 20px;
            text-align: center;
        }
        .metrics-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }
        .metrics-row {
            display: table-row;
        }
        .metric-cell {
            display: table-cell;
            width: 25%;
            padding: 15px;
            text-align: center;
            vertical-align: top;
        }
        .metric-box {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            min-height: 80px;
        }
        .metric-value {
            font-size: 20px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .metric-label {
            font-size: 10px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-growth {
            font-size: 12px;
            margin-top: 5px;
        }
        .growth-positive { color: #28a745; }
        .growth-negative { color: #dc3545; }
        .growth-neutral { color: #6c757d; }
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #495057;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
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
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .recommendations {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        .recommendation-item {
            margin-bottom: 10px;
            padding: 10px;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #ffc107;
        }
        .recommendation-title {
            font-weight: bold;
            color: #856404;
            font-size: 11px;
        }
        .recommendation-text {
            color: #856404;
            font-size: 10px;
            margin-top: 3px;
        }
        .highlights {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
        }
        .highlight-item {
            margin-bottom: 8px;
            color: #0c5460;
            font-size: 10px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 9px;
            color: #666;
            text-align: center;
        }
        .page-break {
            page-break-before: always;
        }
        .chart-placeholder {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>{{ $store->name }}</h1>
        <h2>Monthly Business Report</h2>
        <p>{{ $reportMonth->format('F Y') }}</p>
        <p>Generated on {{ $reportData['generated_at']->format('M j, Y \a\t g:i A') }}</p>
    </div>

    <!-- Executive Summary -->
    <div class="executive-summary">
        <div class="summary-title">📊 Executive Summary</div>
        
        <div class="metrics-grid">
            <div class="metrics-row">
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">${{ number_format($reportData['executive_summary']['revenue']['current'], 0) }}</div>
                        <div class="metric-label">Total Revenue</div>
                        <div class="metric-growth growth-{{ $reportData['executive_summary']['revenue']['growth'] > 0 ? 'positive' : ($reportData['executive_summary']['revenue']['growth'] < 0 ? 'negative' : 'neutral') }}">
                            {{ $reportData['executive_summary']['revenue']['growth'] > 0 ? '+' : '' }}{{ number_format($reportData['executive_summary']['revenue']['growth'], 1) }}% vs last month
                        </div>
                    </div>
                </div>
                
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">{{ number_format($reportData['executive_summary']['orders']['current']) }}</div>
                        <div class="metric-label">Total Orders</div>
                        <div class="metric-growth growth-{{ $reportData['executive_summary']['orders']['growth'] > 0 ? 'positive' : ($reportData['executive_summary']['orders']['growth'] < 0 ? 'negative' : 'neutral') }}">
                            {{ $reportData['executive_summary']['orders']['growth'] > 0 ? '+' : '' }}{{ number_format($reportData['executive_summary']['orders']['growth'], 1) }}% vs last month
                        </div>
                    </div>
                </div>
                
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">${{ number_format($reportData['executive_summary']['profit']['current'], 0) }}</div>
                        <div class="metric-label">Net Profit</div>
                        <div class="metric-growth growth-{{ $reportData['executive_summary']['profit']['growth'] > 0 ? 'positive' : ($reportData['executive_summary']['profit']['growth'] < 0 ? 'negative' : 'neutral') }}">
                            {{ $reportData['executive_summary']['profit']['growth'] > 0 ? '+' : '' }}{{ number_format($reportData['executive_summary']['profit']['growth'], 1) }}% vs last month
                        </div>
                    </div>
                </div>
                
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">${{ number_format($reportData['executive_summary']['orders']['average_order_value'], 2) }}</div>
                        <div class="metric-label">Avg Order Value</div>
                        <div class="metric-growth growth-neutral">
                            {{ number_format($reportData['executive_summary']['profit']['margin'], 1) }}% profit margin
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($reportData['executive_summary']['highlights']))
        <div class="highlights">
            <strong>📈 Monthly Highlights:</strong>
            @foreach($reportData['executive_summary']['highlights'] as $highlight)
            <div class="highlight-item">• {{ $highlight }}</div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Key Performance Indicators -->
    <div class="section">
        <div class="section-title">🎯 Key Performance Indicators</div>
        
        <div class="metrics-grid">
            <div class="metrics-row">
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">${{ number_format($reportData['key_performance_indicators']['revenue_per_day'], 0) }}</div>
                        <div class="metric-label">Revenue per Day</div>
                    </div>
                </div>
                
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">{{ number_format($reportData['key_performance_indicators']['orders_per_day'], 1) }}</div>
                        <div class="metric-label">Orders per Day</div>
                    </div>
                </div>
                
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">{{ number_format($reportData['key_performance_indicators']['customer_retention_rate'], 1) }}%</div>
                        <div class="metric-label">Customer Retention</div>
                    </div>
                </div>
                
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">${{ number_format($reportData['key_performance_indicators']['revenue_per_customer'], 0) }}</div>
                        <div class="metric-label">Revenue per Customer</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Analysis -->
    <div class="section">
        <div class="section-title">📈 Sales Performance</div>
        
        <table>
            <thead>
                <tr>
                    <th>Metric</th>
                    <th class="text-right">Current Month</th>
                    <th class="text-right">Previous Month</th>
                    <th class="text-right">Change</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total Revenue</td>
                    <td class="text-right">${{ number_format($reportData['executive_summary']['revenue']['current'], 2) }}</td>
                    <td class="text-right">${{ number_format($reportData['executive_summary']['revenue']['previous'], 2) }}</td>
                    <td class="text-right growth-{{ $reportData['executive_summary']['revenue']['growth'] > 0 ? 'positive' : ($reportData['executive_summary']['revenue']['growth'] < 0 ? 'negative' : 'neutral') }}">
                        {{ $reportData['executive_summary']['revenue']['growth'] > 0 ? '+' : '' }}{{ number_format($reportData['executive_summary']['revenue']['growth'], 1) }}%
                    </td>
                </tr>
                <tr>
                    <td>Total Orders</td>
                    <td class="text-right">{{ number_format($reportData['executive_summary']['orders']['current']) }}</td>
                    <td class="text-right">{{ number_format($reportData['executive_summary']['orders']['previous']) }}</td>
                    <td class="text-right growth-{{ $reportData['executive_summary']['orders']['growth'] > 0 ? 'positive' : ($reportData['executive_summary']['orders']['growth'] < 0 ? 'negative' : 'neutral') }}">
                        {{ $reportData['executive_summary']['orders']['growth'] > 0 ? '+' : '' }}{{ number_format($reportData['executive_summary']['orders']['growth'], 1) }}%
                    </td>
                </tr>
                <tr>
                    <td>Average Order Value</td>
                    <td class="text-right">${{ number_format($reportData['executive_summary']['orders']['average_order_value'], 2) }}</td>
                    <td class="text-right">${{ number_format($reportData['executive_summary']['revenue']['previous'] / max(1, $reportData['executive_summary']['orders']['previous']), 2) }}</td>
                    <td class="text-right">-</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Top Products -->
    @if(!empty($reportData['product_performance']['products']))
    <div class="section">
        <div class="section-title">🏆 Top Performing Products</div>
        
        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th class="text-right">Quantity Sold</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Profit Margin</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($reportData['product_performance']['products'], 0, 10) as $product)
                <tr>
                    <td>{{ $product['name'] }}</td>
                    <td class="text-right">{{ number_format($product['quantity_sold']) }}</td>
                    <td class="text-right">${{ number_format($product['revenue'], 2) }}</td>
                    <td class="text-right">{{ number_format($product['profit_margin'], 1) }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Page Break -->
    <div class="page-break"></div>

    <!-- Customer Analytics -->
    <div class="section">
        <div class="section-title">👥 Customer Analytics</div>
        
        <div class="metrics-grid">
            <div class="metrics-row">
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">{{ number_format($reportData['customer_analytics']['summary']['unique_customers']) }}</div>
                        <div class="metric-label">Active Customers</div>
                    </div>
                </div>
                
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">{{ number_format($reportData['customer_analytics']['summary']['member_percentage'], 1) }}%</div>
                        <div class="metric-label">Member Orders</div>
                    </div>
                </div>
                
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">${{ number_format($reportData['customer_analytics']['summary']['average_order_value'], 2) }}</div>
                        <div class="metric-label">Avg Customer Value</div>
                    </div>
                </div>
                
                <div class="metric-cell">
                    <div class="metric-box">
                        <div class="metric-value">{{ number_format($reportData['key_performance_indicators']['customer_retention_rate'], 1) }}%</div>
                        <div class="metric-label">Retention Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recommendations -->
    @if(!empty($reportData['recommendations']))
    <div class="section">
        <div class="section-title">💡 Strategic Recommendations</div>
        
        <div class="recommendations">
            @foreach($reportData['recommendations'] as $recommendation)
            <div class="recommendation-item">
                <div class="recommendation-title">{{ $recommendation['title'] }} - {{ ucfirst($recommendation['priority']) }} Priority</div>
                <div class="recommendation-text">{{ $recommendation['description'] }}</div>
                @if(!empty($recommendation['action_items']))
                <div class="recommendation-text">
                    <strong>Action Items:</strong>
                    @foreach($recommendation['action_items'] as $action)
                    • {{ $action }}
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Business Insights -->
    @if(!empty($reportData['business_insights']))
    <div class="section">
        <div class="section-title">🔍 Business Insights</div>
        
        @foreach($reportData['business_insights'] as $category => $insights)
            @if(!empty($insights) && is_array($insights))
            <div style="margin-bottom: 15px;">
                <strong>{{ ucfirst(str_replace('_', ' ', $category)) }}:</strong>
                @foreach($insights as $insight)
                <div style="margin: 5px 0; padding-left: 15px;">• {{ $insight }}</div>
                @endforeach
            </div>
            @endif
        @endforeach
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This automated monthly report was generated by POS Xpress Analytics Engine</p>
        <p>Report Period: {{ $reportData['report_period']['start_date'] }} to {{ $reportData['report_period']['end_date'] }}</p>
        <p>Generated on {{ $reportData['generated_at']->format('M j, Y \a\t g:i A') }} | © {{ date('Y') }} POS Xpress. All rights reserved.</p>
    </div>
</body>
</html>