<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Langganan XpressPOS Diperpanjang</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8fafc;
        }
        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .title {
            color: #1f2937;
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }
        .subtitle {
            color: #6b7280;
            font-size: 16px;
            margin: 10px 0 0 0;
        }
        .success-box {
            background: #ecfdf5;
            border: 2px solid #10b981;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
        }
        .success-title {
            color: #065f46;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .success-text {
            color: #047857;
            font-size: 14px;
        }
        .login-button {
            display: inline-block;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            text-decoration: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            text-align: center;
            margin: 20px 0;
            transition: transform 0.2s;
        }
        .login-button:hover {
            transform: translateY(-2px);
        }
        .info-box {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-title {
            color: #374151;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .info-item {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #6b7280;
            font-size: 14px;
            display: block;
        }
        .info-value {
            color: #1f2937;
            font-weight: 600;
            font-size: 16px;
        }
        .support-box {
            background: #eff6ff;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            text-align: center;
        }
        .support-title {
            color: #1d4ed8;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .support-text {
            color: #1e40af;
            font-size: 14px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">✓</div>
            <h1 class="title">Langganan Diperpanjang!</h1>
            <p class="subtitle">Terima kasih telah memperpanjang langganan XpressPOS</p>
        </div>

        <!-- Welcome Message -->
        <p>Halo <strong>{{ $user->name }}</strong>,</p>
        
        <p>Pembayaran untuk perpanjangan langganan <strong>XpressPOS {{ $planName }}</strong> telah berhasil diproses! Langganan untuk bisnis <strong>{{ $businessName }}</strong> telah diperpanjang dan tetap aktif.</p>

        <!-- Success Confirmation -->
        <div class="success-box">
            <div class="success-title">🎉 Langganan Berhasil Diperpanjang</div>
            <div class="success-text">
                Akun Anda tetap aktif dan semua fitur XpressPOS dapat terus digunakan tanpa gangguan.
            </div>
        </div>

        <!-- Login Button -->
        <div style="text-align: center;">
            <a href="{{ $loginUrl }}" class="login-button">
                🚀 Masuk ke Dashboard Owner
            </a>
        </div>

        <!-- Subscription Details -->
        <div class="info-box">
            <div class="info-title">📊 Detail Langganan Anda:</div>
            
            <div class="info-item">
                <span class="info-label">Paket:</span>
                <div class="info-value">XpressPOS {{ $planName }}</div>
            </div>
            
            <div class="info-item">
                <span class="info-label">Bisnis:</span>
                <div class="info-value">{{ $businessName }}</div>
            </div>
            
            <div class="info-item">
                <span class="info-label">Email:</span>
                <div class="info-value">{{ $user->email }}</div>
            </div>
            
            <div class="info-item">
                <span class="info-label">Status:</span>
                <div class="info-value" style="color: #10b981;">✅ Aktif</div>
            </div>
        </div>

        <!-- What's Next -->
        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #374151; margin-bottom: 15px;">🚀 Apa Selanjutnya?</h4>
            <ul style="color: #6b7280; font-size: 14px; margin: 0; padding-left: 20px;">
                <li>Akses dashboard owner Anda dengan akun yang sudah ada</li>
                <li>Semua data dan pengaturan toko tetap tersimpan</li>
                <li>Lanjutkan menggunakan semua fitur XpressPOS</li>
                <li>Nikmati layanan tanpa gangguan</li>
            </ul>
        </div>

        <!-- Support Information -->
        <div class="support-box">
            <div class="support-title">🤝 Butuh Bantuan?</div>
            <div class="support-text">
                Tim support kami siap membantu Anda 24/7<br>
                📧 Email: support@xpresspos.id<br>
                📞 WhatsApp: +62 21 1234 5678<br>
                💬 Live Chat tersedia di dashboard
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Email ini dikirim otomatis oleh sistem XpressPOS.<br>
            Terima kasih telah mempercayai XpressPOS untuk bisnis Anda.</p>
            
            <p style="margin-top: 15px;">
                <strong>XpressPOS</strong> - Maksimalkan Bisnismu<br>
                © {{ date('Y') }} XpressPOS. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>