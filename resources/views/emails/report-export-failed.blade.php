<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Export Failed - {{ $reportType }}</title>
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
        .format-badge {
            display: inline-block;
            background: #6c757d;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="error-icon">❌</div>
            <h1>Report Export Failed</h1>
            <h2>{{ $reportType }} Report</h2>
            <p>We encountered an issue while generating your report</p>
        </div>

        <div class="error-section">
            <h3>⚠️ Export Error</h3>
            <p>Unfortunately, we were unable to generate your {{ $reportType }} report in {{ $format }} format.</p>

            <div class="error-details">
                <strong>Error Details:</strong><br>
                {{ $errorMessage }}
            </div>
        </div>

        <div class="retry-section">
            <h3>🔄 What You Can Do</h3>
            <ul style="color: #0056b3; margin: 10px 0; padding-left: 20px;">
                <li><strong>Try Again:</strong> The issue might be temporary. Please try generating the report again.</li>
                <li><strong>Check Date Range:</strong> Ensure your selected date range contains data and is reasonable.</li>
                <li><strong>Try Different Format:</strong> If you selected PDF, try Excel format or vice versa.</li>
                <li><strong>Reduce Data Scope:</strong> Try generating the report for a shorter date range.</li>
            </ul>

            <div style="text-align: center;">
                <a href="#" class="cta-button">🔄 Retry Report Generation</a>
            </div>
        </div>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; font-size: 14px; color: #666;">
            <strong>💡 Common Solutions:</strong><br>
            • Verify you have sufficient data in the selected date range<br>
            • Check if your store has the necessary permissions for report generation<br>
            • Ensure your subscription plan includes report export features<br>
            • Try generating reports during off-peak hours for better performance
        </div>

        <div class="support-section">
            <strong>🆘 Need Help?</strong>
            If you continue to experience issues, please contact our support team with the error details above.
            We're here to help you get the reports you need.
        </div>

        <div style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px; margin: 20px 0; color: #0056b3; font-size: 14px;">
            <strong>📊 Alternative Report Options:</strong><br>
            • Use the dashboard for real-time data visualization<br>
            • Generate reports for shorter time periods<br>
            • Export individual data sections instead of comprehensive reports<br>
            • Contact support for custom report generation
        </div>

        <div class="footer">
            <p>This error notification was sent automatically by POS Xpress</p>
            <p>Generated on {{ date('M j, Y \a\t g:i A') }}</p>
            <p>© {{ date('Y') }} POS Xpress. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
