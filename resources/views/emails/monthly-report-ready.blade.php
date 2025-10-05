<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report Ready - {{ $store->name }}</title>
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
            border-bottom: 3px solid #007bff;
        }
        .header h1 {
            color: #007bff;
            margin: 0;
            font-size: 28px;
        }
        .header h2 {
            color: #666;
            margin: 10px 0;
            font-size: 18px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 30px 0;
        }
        .metric-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #007bff;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        .metric-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .growth {
            font-size: 14px;
            margin-top: 5px;
        }
        .growth.positive { color: #28a745; }
        .growth.negative { color: #dc3545; }
        .growth.neutral { color: #6c757d; }
        .highlights {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .highlights h3 {
            margin-top: 0;
            color: #0056b3;
        }
        .highlight-item {
            margin: 8px 0;
            color: #0056b3;
        }
        .recommendations {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .recommendations h3 {
            margin-top: 0;
            color: #856404;
        }
        .recommendation-item {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 3px solid #ffc107;
        }
        .recommendation-title {
            font-weight: bold;
            color: #856404;
            margin-bottom: 5px;
        }
        .recommendation-text {
            color: #856404;
            font-size: 14px;
        }
        .cta-button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
        }
        .cta-button:hover {
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
        .attachment-notice {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #155724;
        }
        .attachment-notice strong {
            display: block;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $store->name }}</h1>
            <h2>Monthly Report Ready</h2>
            <p>{{ $reportMonth->format('F Y') }}</p>
        </div>

        <div class="attachment-notice">
            <strong>📊 Your Monthly Report is Ready!</strong>
            Your comprehensive monthly business report has been generated and is attached to this email. The PDF contains detailed analytics, insights, and recommendations for {{ $reportMonth->format('F Y') }}.
        </div>

        <!-- Executive Summary Preview -->
        <h3 style="color: #007bff; border-bottom: 2px solid #e9ecef; padding-bottom: 10px;">📈 Executive Summary</h3>

        <div class="summary-grid">
            <div class="metric-card">
                <div class="metric-value">${{ number_format($executiveSummary['revenue']['current'], 0) }}</div>
                <div class="metric-label">Total Revenue</div>
                <div class="growth {{ $executiveSummary['revenue']['growth'] > 0 ? 'positive' : ($executiveSummary['revenue']['growth'] < 0 ? 'negative' : 'neutral') }}">
                    {{ $executiveSummary['revenue']['growth'] > 0 ? '+' : '' }}{{ number_format($executiveSummary['revenue']['growth'], 1) }}% vs last month
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-value">{{ number_format($executiveSummary['orders']['current']) }}</div>
                <div class="metric-label">Total Orders</div>
                <div class="growth {{ $executiveSummary['orders']['growth'] > 0 ? 'positive' : ($executiveSummary['orders']['growth'] < 0 ? 'negative' : 'neutral') }}">
                    {{ $executiveSummary['orders']['growth'] > 0 ? '+' : '' }}{{ number_format($executiveSummary['orders']['growth'], 1) }}% vs last month
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-value">${{ number_format($executiveSummary['profit']['current'], 0) }}</div>
                <div class="metric-label">Net Profit</div>
                <div class="growth {{ $executiveSummary['profit']['growth'] > 0 ? 'positive' : ($executiveSummary['profit']['growth'] < 0 ? 'negative' : 'neutral') }}">
                    {{ $executiveSummary['profit']['growth'] > 0 ? '+' : '' }}{{ number_format($executiveSummary['profit']['growth'], 1) }}% vs last month
                </div>
            </div>

            <div class="metric-card">
                <div class="metric-value">{{ number_format($kpis['customer_retention_rate'], 1) }}%</div>
                <div class="metric-label">Customer Retention</div>
                <div class="growth neutral">
                    {{ number_format($kpis['unique_customers']) }} active customers
                </div>
            </div>
        </div>

        <!-- Key Highlights -->
        @if(!empty($executiveSummary['highlights']))
        <div class="highlights">
            <h3>🎯 Key Highlights</h3>
            @foreach($executiveSummary['highlights'] as $highlight)
            <div class="highlight-item">• {{ $highlight }}</div>
            @endforeach
        </div>
        @endif

        <!-- Top Recommendations -->
        @if(!empty($recommendations) && count($recommendations) > 0)
        <div class="recommendations">
            <h3>💡 Top Recommendations</h3>
            @foreach(array_slice($recommendations, 0, 3) as $recommendation)
            <div class="recommendation-item">
                <div class="recommendation-title">{{ $recommendation['title'] }} - {{ ucfirst($recommendation['priority']) }} Priority</div>
                <div class="recommendation-text">{{ $recommendation['description'] }}</div>
            </div>
            @endforeach
        </div>
        @endif

        <div style="text-align: center;">
            <a href="#" class="cta-button">📊 View Full Report (PDF Attached)</a>
        </div>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; font-size: 14px; color: #666;">
            <strong>📋 What's in your report:</strong><br>
            • Comprehensive financial performance analysis<br>
            • Sales trends and product performance<br>
            • Customer analytics and retention metrics<br>
            • Strategic recommendations and action items<br>
            • Business insights and growth opportunities
        </div>

        <div class="footer">
            <p>This automated monthly report was generated by POS Xpress Analytics Engine</p>
            <p>Report Period: {{ $reportData['report_period']['start_date'] }} to {{ $reportData['report_period']['end_date'] }}</p>
            <p>Generated on {{ $reportData['generated_at']->format('M j, Y \a\t g:i A') }}</p>
            <p>© {{ date('Y') }} POS Xpress. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
