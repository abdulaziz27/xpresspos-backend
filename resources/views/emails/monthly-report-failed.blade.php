<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monthly Report Failed - {{ $storeName }}</title>
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
            font-size: 24px;
        }
        .header h2 {
            color: #666;
            margin: 10px 0;
            font-size: 18px;
        }
        .error-icon {
            font-size: 48px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .error-section {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }
        .error-section h3 {
            margin-top: 0;
            color: #721c24;
        }
        .error-details {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 14px;
            color: #495057;
        }
        .retry-section {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .retry-section h3 {
            margin-top: 0;
            color: #0056b3;
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
            font-size: 16px;
        }
        .cta-button:hover {
            background: #0056b3;
        }
        .support-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
            font-size: 14px;
        }
        .support-section strong {
            display: block;
            margin-bottom: 5px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        .store-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            color: #495057;
        }
        .store-info strong {
            display: block;
            margin-bottom: 5px;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="error-icon">❌</div>
            <h1>Monthly Report Failed</h1>
            <h2>{{ $storeName }}</h2>
            <p>We encountered an issue generating your monthly report</p>
        </div>

        <div class="store-info">
            <strong>Store Information:</strong>
            Store: {{ $storeName }}<br>
            Report Period: {{ $reportMonth }}<br>
            Failed At: {{ date('M j, Y \a\t g:i A') }}
        </div>

        <div class="error-section">
            <h3>⚠️ Report Generation Error</h3>
            <p>We were unable to generate your comprehensive monthly report for {{ $reportMonth }}.</p>

            <div class="error-details">
                <strong>Error Details:</strong><br>
                {{ $errorMessage }}
            </div>
        </div>

        <div class="retry-section">
            <h3>🔄 What You Can Do</h3>
            <ul style="color: #0056b3; margin: 10px 0; padding-left: 20px;">
                <li><strong>Check Data Availability:</strong> Ensure you have sufficient transaction data for {{ $reportMonth }}</li>
                <li><strong>Verify Store Configuration:</strong> Make sure your store settings are properly configured</li>
                <li><strong>Try Manual Generation:</strong> You can manually trigger report generation from your dashboard</li>
                <li><strong>Contact Support:</strong> Our team can help investigate and resolve the issue</li>
            </ul>

            <div style="text-align: center;">
                <a href="#" class="cta-button">📊 Access Dashboard</a>
            </div>
        </div>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; font-size: 14px; color: #666;">
            <strong>💡 Monthly Report Includes:</strong><br>
            • Executive summary with key performance metrics<br>
            • Financial performance analysis<br>
            • Sales trends and product performance<br>
            • Customer analytics and retention metrics<br>
            • Strategic recommendations and insights<br>
            • Comprehensive PDF report with charts and tables
        </div>

        <div style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px; margin: 20px 0; color: #0056b3; font-size: 14px;">
            <strong>📈 Alternative Data Access:</strong><br>
            • View real-time metrics in your dashboard<br>
            • Generate custom reports for specific date ranges<br>
            • Export individual data sections (sales, inventory, etc.)<br>
            • Access historical data through the reporting section
        </div>

        <div class="support-section">
            <strong>🆘 Need Immediate Assistance?</strong>
            If you need your monthly report urgently or continue to experience issues, please contact our support team.
            We can help generate your report manually and investigate the underlying cause.
        </div>

        <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #0c5460;">📋 Next Steps</h4>
            <ol style="color: #0c5460; margin: 10px 0; padding-left: 20px;">
                <li>Check if you have sufficient data for {{ $reportMonth }}</li>
                <li>Verify your store configuration and settings</li>
                <li>Try generating reports for individual components</li>
                <li>Contact support if the issue persists</li>
                <li>We'll investigate and resolve the issue promptly</li>
            </ol>
        </div>

        <div class="footer">
            <p>This error notification was sent automatically by POS Xpress Analytics Engine</p>
            <p>Store: {{ $storeName }} | Report Period: {{ $reportMonth }}</p>
            <p>Generated on {{ date('M j, Y \a\t g:i A') }}</p>
            <p>© {{ date('Y') }} POS Xpress. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
