<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Product Performance Report - {{ $storeName }}</title>
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
            border-bottom: 2px solid #6f42c1;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #6f42c1;
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
            border-left: 4px solid #6f42c1;
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
        .profit-margin {
            font-weight: bold;
        }
        .profit-margin.high {
            color: #28a745;
        }
        .profit-margin.medium {
            color: #ffc107;
        }
        .profit-margin.low {
            color: #dc3545;
        }
        .performance-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .performance-excellent {
            background-color: #d4edda;
            color: #155724;
        }
        .performance-good {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .performance-average {
            background-color: #fff3cd;
            color: #856404;
        }
        .performance-poor {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $storeName }}</h1>
        <h2>Product Performance Report</h2>
        <p>{{ $data['period']['start_date'] }} to {{ $data['period']['end_date'] }}</p>
        <p>Generated on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
    </div>

    <!-- Summary Metrics -->
    <div class="summary-grid">
        <div class="summary-row">
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Products Sold</div>
                    <div class="metric-value">{{ number_format($data['summary']['total_products_sold']) }}</div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Total Quantity</div>
                    <div class="metric-value">{{ number_format($data['summary']['total_quantity']) }}</div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Total Revenue</div>
                    <div class="metric-value">${{ number_format($data['summary']['total_revenue'], 0) }}</div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Total Profit</div>
                    <div class="metric-value">${{ number_format($data['summary']['total_profit'], 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overall Performance Metrics -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 30px 0;">
        <h4 style="margin-top: 0; color: #495057;">📊 Overall Performance</h4>
        <div style="display: table; width: 100%;">
            <div style="display: table-row;">
                <div style="display: table-cell; width: 33.33%; padding: 10px;">
                    <strong>Average Profit Margin:</strong> {{ number_format($data['summary']['average_profit_margin'], 1) }}%
                </div>
                <div style="display: table-cell; width: 33.33%; padding: 10px;">
                    <strong>Sort By:</strong> {{ ucfirst(str_replace('_', ' ', $data['sort_by'])) }}
                </div>
                <div style="display: table-cell; width: 33.33%; padding: 10px;">
                    <strong>Report Limit:</strong> Top {{ $data['limit'] }} products
                </div>
            </div>
        </div>
    </div>

    <!-- Product Performance Table -->
    <div class="section">
        <div class="section-title">🏆 Product Performance Ranking</div>

        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Product Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th class="text-right">Quantity Sold</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Profit</th>
                    <th class="text-right">Profit Margin</th>
                    <th class="text-right">Orders</th>
                    <th class="text-center">Performance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['products'] as $index => $product)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $product['name'] }}</td>
                    <td>{{ $product['sku'] }}</td>
                    <td>{{ $product['category'] }}</td>
                    <td class="text-right">{{ number_format($product['quantity_sold']) }}</td>
                    <td class="text-right">${{ number_format($product['revenue'], 2) }}</td>
                    <td class="text-right">${{ number_format($product['profit'], 2) }}</td>
                    <td class="text-right profit-margin {{ $product['profit_margin'] >= 30 ? 'high' : ($product['profit_margin'] >= 15 ? 'medium' : 'low') }}">
                        {{ number_format($product['profit_margin'], 1) }}%
                    </td>
                    <td class="text-right">{{ number_format($product['order_count']) }}</td>
                    <td class="text-center">
                        @php
                            $performance = 'poor';
                            if ($product['profit_margin'] >= 30 && $product['quantity_sold'] >= 50) {
                                $performance = 'excellent';
                            } elseif ($product['profit_margin'] >= 20 && $product['quantity_sold'] >= 20) {
                                $performance = 'good';
                            } elseif ($product['profit_margin'] >= 10 || $product['quantity_sold'] >= 10) {
                                $performance = 'average';
                            }
                        @endphp
                        <span class="performance-badge performance-{{ $performance }}">
                            {{ $performance }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Top Performers Analysis -->
    <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 30px 0;">
        <h4 style="margin-top: 0; color: #155724;">🌟 Top Performers Analysis</h4>

        @if(!empty($data['products']))
            @php
                $topProduct = $data['products'][0];
                $highMarginProducts = array_filter($data['products'], function($p) { return $p['profit_margin'] >= 30; });
                $highVolumeProducts = array_filter($data['products'], function($p) { return $p['quantity_sold'] >= 50; });
            @endphp

            <ul style="color: #155724; margin: 10px 0; padding-left: 20px;">
                <li><strong>Best Selling Product:</strong> {{ $topProduct['name'] }} with {{ number_format($topProduct['quantity_sold']) }} units sold</li>
                <li><strong>Highest Revenue Product:</strong> {{ $topProduct['name'] }} generating ${{ number_format($topProduct['revenue'], 2) }}</li>
                <li><strong>High Margin Products (≥30%):</strong> {{ count($highMarginProducts) }} products</li>
                <li><strong>High Volume Products (≥50 units):</strong> {{ count($highVolumeProducts) }} products</li>
                @if($topProduct['profit_margin'] >= 30)
                <li><strong>Top Performer:</strong> {{ $topProduct['name'] }} combines high volume ({{ number_format($topProduct['quantity_sold']) }} units) with excellent margin ({{ number_format($topProduct['profit_margin'], 1) }}%)</li>
                @endif
            </ul>
        @endif
    </div>

    <!-- Recommendations -->
    <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 30px 0;">
        <h4 style="margin-top: 0; color: #856404;">💡 Strategic Recommendations</h4>

        @if(!empty($data['products']))
            @php
                $lowPerformers = array_filter($data['products'], function($p) { return $p['profit_margin'] < 10 && $p['quantity_sold'] < 10; });
                $mediumPerformers = array_filter($data['products'], function($p) { return $p['profit_margin'] >= 10 && $p['profit_margin'] < 20; });
            @endphp

            <ul style="color: #856404; margin: 10px 0; padding-left: 20px;">
                @if(count($lowPerformers) > 0)
                <li><strong>Review Low Performers:</strong> {{ count($lowPerformers) }} products have both low margins and low sales volume. Consider discontinuing or repositioning these items.</li>
                @endif

                @if(count($mediumPerformers) > 0)
                <li><strong>Optimize Medium Performers:</strong> {{ count($mediumPerformers) }} products show potential for improvement. Consider price adjustments or marketing efforts.</li>
                @endif

                @if(count($highMarginProducts) > 0)
                <li><strong>Promote High Margin Products:</strong> {{ count($highMarginProducts) }} products have excellent profit margins. Focus marketing efforts on these items.</li>
                @endif

                @if(count($highVolumeProducts) > 0)
                <li><strong>Maintain Stock Levels:</strong> {{ count($highVolumeProducts) }} high-volume products should be prioritized for inventory management.</li>
                @endif
            </ul>
        @endif
    </div>

    <div class="footer">
        <p>This product performance report was generated automatically by POS Xpress on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
        <p>© {{ date('Y') }} POS Xpress. All rights reserved.</p>
    </div>
</body>
</html>
