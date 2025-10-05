<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Export Ready - {{ $reportType }}</title>
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
            border-bottom: 3px solid #28a745;
        }
        .header h1 {
            color: #28a745;
            margin: 0;
            font-size: 24px;
        }
        .header h2 {
            color: #666;
            margin: 10px 0;
            font-size: 18px;
        }
        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .download-section {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }
        .download-section h3 {
            margin-top: 0;
            color: #155724;
        }
        .file-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .file-name {
            font-weight: bold;
            color: #495057;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .file-details {
            color: #6c757d;
            font-size: 14px;
        }
        .cta-button {
            display: inline-block;
            background: #28a745;
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
            background: #218838;
        }
        .expiry-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
            font-size: 14px;
        }
        .expiry-notice strong {
            display: block;
            margin-bottom: 5px;
        }
        .instructions {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .instructions h3 {
            margin-top: 0;
            color: #0056b3;
        }
        .instructions ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .instructions li {
            margin: 5px 0;
            color: #0056b3;
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
            background: #007bff;
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
            <div class="success-icon">✅</div>
            <h1>Report Export Ready!</h1>
            <h2>{{ $reportType }} Report</h2>
            <p>Your requested report has been successfully generated</p>
        </div>

        <div class="download-section">
            <h3>📊 Download Your Report</h3>
            <div class="file-info">
                <div class="file-name">{{ $fileName }}</div>
                <div class="file-details">
                    Format: <span class="format-badge">{{ $format }}</span><br>
                    Generated: {{ date('M j, Y \a\t g:i A') }}
                </div>
            </div>

            <a href="{{ $downloadUrl }}" class="cta-button">📥 Download Report</a>
        </div>

        <div class="expiry-notice">
            <strong>⏰ Download Link Expires</strong>
            This download link will expire on {{ $expiresAt }}. Please download your report before then.
        </div>

        <div class="instructions">
            <h3>📋 How to Download</h3>
            <ul>
                <li>Click the "Download Report" button above</li>
                <li>Your browser will download the {{ $format }} file</li>
                <li>Open the file with the appropriate application:
                    @if($format === 'PDF')
                    <ul>
                        <li>Adobe Acrobat Reader</li>
                        <li>Chrome/Edge browser</li>
                        <li>Any PDF viewer</li>
                    </ul>
                    @else
                    <ul>
                        <li>Microsoft Excel</li>
                        <li>Google Sheets</li>
                        <li>LibreOffice Calc</li>
                    </ul>
                    @endif
                </li>
                <li>Save the file to your preferred location</li>
            </ul>
        </div>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; font-size: 14px; color: #666;">
            <strong>💡 Report Contents:</strong><br>
            @if(str_contains($reportType, 'Sales'))
            • Sales performance metrics and trends<br>
            • Revenue analysis by period<br>
            • Top-selling products and categories<br>
            • Payment method breakdown
            @elseif(str_contains($reportType, 'Inventory'))
            • Current stock levels and status<br>
            • Low stock alerts<br>
            • Inventory movements and history<br>
            • Stock valuation and COGS analysis
            @elseif(str_contains($reportType, 'Cash Flow'))
            • Daily cash flow analysis<br>
            • Payment method performance<br>
            • Expense tracking and categorization<br>
            • Cash session summaries
            @elseif(str_contains($reportType, 'Product Performance'))
            • Product sales rankings<br>
            • Profit margin analysis<br>
            • ABC analysis categorization<br>
            • Product lifecycle insights
            @elseif(str_contains($reportType, 'Customer Analytics'))
            • Customer segmentation analysis<br>
            • RFM analysis results<br>
            • Customer lifetime value<br>
            • Retention and churn metrics
            @else
            • Comprehensive business analytics<br>
            • Performance metrics and KPIs<br>
            • Strategic insights and recommendations<br>
            • Detailed data breakdowns
            @endif
        </div>

        <div style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 5px; padding: 15px; margin: 20px 0; color: #0056b3; font-size: 14px;">
            <strong>🔄 Need Another Report?</strong><br>
            You can generate additional reports anytime through your POS Xpress dashboard. Visit the Reports section to create custom reports with different date ranges and filters.
        </div>

        <div class="footer">
            <p>This report was generated automatically by POS Xpress Analytics Engine</p>
            <p>Generated on {{ date('M j, Y \a\t g:i A') }}</p>
            <p>© {{ date('Y') }} POS Xpress. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
