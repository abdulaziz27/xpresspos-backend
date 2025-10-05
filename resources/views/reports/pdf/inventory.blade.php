<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inventory Report - {{ $storeName }}</title>
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
            width: 25%;
            padding: 10px;
            vertical-align: top;
        }
        .metric-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #007bff;
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
        .status-badge {
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-in-stock {
            background-color: #d4edda;
            color: #155724;
        }
        .status-low-stock {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 10px;
            color: #666;
            text-align: center;
        }
        .alert-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            color: #856404;
        }
        .alert-box h4 {
            margin-top: 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $storeName }}</h1>
        <h2>Inventory Report</h2>
        <p>Generated on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
    </div>

    <!-- Summary Metrics -->
    <div class="summary-grid">
        <div class="summary-row">
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Total Products</div>
                    <div class="metric-value">{{ number_format($data['summary']['total_products']) }}</div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Low Stock Items</div>
                    <div class="metric-value">{{ number_format($data['summary']['low_stock_products']) }}</div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Out of Stock</div>
                    <div class="metric-value">{{ number_format($data['summary']['out_of_stock_products']) }}</div>
                </div>
            </div>
            <div class="summary-cell">
                <div class="metric-box">
                    <div class="metric-label">Total Stock Value</div>
                    <div class="metric-value">${{ number_format($data['summary']['total_stock_value'], 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    @if($data['summary']['low_stock_products'] > 0 || $data['summary']['out_of_stock_products'] > 0)
    <div class="alert-box">
        <h4>⚠️ Stock Alert</h4>
        @if($data['summary']['out_of_stock_products'] > 0)
        <p><strong>{{ $data['summary']['out_of_stock_products'] }} products are out of stock</strong> and need immediate attention.</p>
        @endif
        @if($data['summary']['low_stock_products'] > 0)
        <p><strong>{{ $data['summary']['low_stock_products'] }} products are running low on stock</strong> and should be reordered soon.</p>
        @endif
    </div>
    @endif

    <!-- Inventory Details -->
    <div class="section">
        <div class="section-title">📦 Inventory Details</div>

        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th class="text-right">Current Stock</th>
                    <th class="text-right">Min Level</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Cost Price</th>
                    <th class="text-right">Selling Price</th>
                    <th class="text-right">Stock Value</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['products'] as $product)
                <tr>
                    <td>{{ $product['name'] }}</td>
                    <td>{{ $product['sku'] }}</td>
                    <td>{{ $product['category'] }}</td>
                    <td class="text-right">{{ number_format($product['current_stock']) }}</td>
                    <td class="text-right">{{ number_format($product['min_stock_level']) }}</td>
                    <td class="text-center">
                        <span class="status-badge status-{{ str_replace('_', '-', $product['status']) }}">
                            {{ str_replace('_', ' ', $product['status']) }}
                        </span>
                    </td>
                    <td class="text-right">${{ number_format($product['cost_price'], 2) }}</td>
                    <td class="text-right">${{ number_format($product['selling_price'], 2) }}</td>
                    <td class="text-right">${{ number_format($product['stock_value'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Low Stock Items (if any) -->
    @if($data['summary']['low_stock_products'] > 0)
    <div class="section">
        <div class="section-title">⚠️ Low Stock Items</div>

        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>SKU</th>
                    <th class="text-right">Current Stock</th>
                    <th class="text-right">Min Level</th>
                    <th class="text-right">Reorder Quantity</th>
                    <th class="text-right">Estimated Cost</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['products'] as $product)
                    @if($product['status'] === 'low_stock')
                    <tr>
                        <td>{{ $product['name'] }}</td>
                        <td>{{ $product['sku'] }}</td>
                        <td class="text-right">{{ number_format($product['current_stock']) }}</td>
                        <td class="text-right">{{ number_format($product['min_stock_level']) }}</td>
                        <td class="text-right">{{ number_format($product['min_stock_level'] * 2) }}</td>
                        <td class="text-right">${{ number_format(($product['min_stock_level'] * 2) * $product['cost_price'], 2) }}</td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Out of Stock Items (if any) -->
    @if($data['summary']['out_of_stock_products'] > 0)
    <div class="section">
        <div class="section-title">❌ Out of Stock Items</div>

        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th class="text-right">Selling Price</th>
                    <th class="text-right">Potential Revenue Loss</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['products'] as $product)
                    @if($product['status'] === 'out_of_stock')
                    <tr>
                        <td>{{ $product['name'] }}</td>
                        <td>{{ $product['sku'] }}</td>
                        <td>{{ $product['category'] }}</td>
                        <td class="text-right">${{ number_format($product['selling_price'], 2) }}</td>
                        <td class="text-right">${{ number_format($product['min_stock_level'] * $product['selling_price'], 2) }}</td>
                    </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <p>This inventory report was generated automatically by POS Xpress on {{ $generatedAt->format('M j, Y \a\t g:i A') }}</p>
        <p>© {{ date('Y') }} POS Xpress. All rights reserved.</p>
    </div>
</body>
</html>
