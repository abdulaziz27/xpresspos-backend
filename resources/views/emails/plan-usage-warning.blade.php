<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peringatan Penggunaan Plan - {{ $featureLabel }}</title>
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
            border-bottom: 3px solid {{ $isCritical ? '#dc3545' : '#ffc107' }};
        }
        .header h1 {
            color: {{ $isCritical ? '#dc3545' : '#856404' }};
            margin: 0;
            font-size: 28px;
        }
        .header .warning-icon {
            font-size: 48px;
            color: {{ $isCritical ? '#dc3545' : '#ffc107' }};
            margin-bottom: 10px;
        }
        .usage-summary {
            background: {{ $isCritical ? '#f8d7da' : '#fff3cd' }};
            border: 1px solid {{ $isCritical ? '#f5c6cb' : '#ffeaa7' }};
            border-radius: 8px;
            padding: 25px;
            margin: 25px 0;
            text-align: center;
        }
        .usage-summary h2 {
            color: {{ $isCritical ? '#721c24' : '#856404' }};
            margin: 0 0 15px 0;
            font-size: 24px;
        }
        .usage-stats {
            display: flex;
            justify-content: space-around;
            margin: 20px 0;
            flex-wrap: wrap;
        }
        .stat-box {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 10px;
            min-width: 120px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: {{ $isCritical ? '#dc3545' : '#856404' }};
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background-color: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, {{ $isCritical ? '#dc3545' : '#ffc107' }} 0%, {{ $isCritical ? '#c82333' : '#e0a800' }} 100%);
            width: {{ min(100, $usagePercentage) }}%;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        .content {
            margin: 25px 0;
        }
        .content p {
            margin: 15px 0;
            color: #555;
        }
        .action-button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
            text-align: center;
        }
        .action-button:hover {
            background-color: #0056b3;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .info-box {
            background-color: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box p {
            margin: 5px 0;
            color: #004085;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="warning-icon">{{ $isCritical ? '🚨' : '⚠️' }}</div>
            <h1>{{ $isCritical ? 'Limit Tercapai!' : 'Peringatan Penggunaan' }}</h1>
        </div>

        <div class="usage-summary">
            <h2>{{ $isCritical ? 'Limit ' . $featureLabel . ' Telah Tercapai' : 'Penggunaan ' . $featureLabel . ' Mencapai ' . number_format($usagePercentage, 1) . '%' }}</h2>
            <p style="margin: 10px 0; color: #666;">
                Tenant: <strong>{{ $tenantName }}</strong><br>
                Plan: <strong>{{ $planName }}</strong>
            </p>
        </div>

        <div class="usage-stats">
            <div class="stat-box">
                <div class="stat-label">Penggunaan Saat Ini</div>
                <div class="stat-value">{{ number_format($currentUsage) }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Limit Plan</div>
                <div class="stat-value">{{ number_format($limit) }}</div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Sisa</div>
                <div class="stat-value" style="color: {{ $remaining > 0 ? '#28a745' : '#dc3545' }};">
                    {{ number_format($remaining) }}
                </div>
            </div>
        </div>

        <div class="progress-bar">
            <div class="progress-fill">
                {{ number_format($usagePercentage, 1) }}%
            </div>
        </div>

        <div class="content">
            @if($isCritical)
                <p><strong>Penting:</strong> Anda telah mencapai limit {{ strtolower($featureLabel) }} untuk plan {{ $planName }}.</p>
                <p>Untuk melanjutkan menggunakan fitur ini, silakan upgrade ke plan yang lebih tinggi atau hubungi tim support kami untuk informasi lebih lanjut.</p>
            @else
                <p>Penggunaan {{ strtolower($featureLabel) }} Anda telah mencapai <strong>{{ number_format($usagePercentage, 1) }}%</strong> dari limit plan {{ $planName }}.</p>
                <p>Anda masih memiliki <strong>{{ number_format($remaining) }}</strong> {{ strtolower($featureLabel) }} tersisa sebelum mencapai limit.</p>
                <p>Pertimbangkan untuk upgrade ke plan yang lebih tinggi jika Anda membutuhkan lebih banyak {{ strtolower($featureLabel) }}.</p>
            @endif

            <div class="info-box">
                <p><strong>💡 Tips:</strong></p>
                <p>• Upgrade ke plan yang lebih tinggi untuk mendapatkan limit yang lebih besar</p>
                <p>• Hubungi tim support kami jika Anda membutuhkan bantuan</p>
                <p>• Pantau penggunaan Anda secara berkala di dashboard</p>
            </div>
        </div>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $upgradeUrl }}" class="action-button">
                {{ $isCritical ? 'Upgrade Plan Sekarang' : 'Lihat Detail Plan' }}
            </a>
        </div>

        <div class="footer">
            <p>Email ini dikirim secara otomatis oleh sistem XpressPOS.</p>
            <p>Jika Anda memiliki pertanyaan, silakan hubungi tim support kami.</p>
            <p>&copy; {{ date('Y') }} XpressPOS. All rights reserved.</p>
        </div>
    </div>
</body>
</html>

