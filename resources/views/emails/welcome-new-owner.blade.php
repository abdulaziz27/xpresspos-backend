<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Datang di XpressPOS</title>
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
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
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
        .credentials-box {
            background: #f3f4f6;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .credentials-title {
            color: #374151;
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .credential-item {
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .credential-item:last-child {
            border-bottom: none;
        }
        .credential-label {
            color: #6b7280;
            font-size: 14px;
            display: block;
        }
        .credential-value {
            color: #1f2937;
            font-weight: 600;
            font-size: 16px;
            font-family: 'Courier New', monospace;
            background: white;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
        }
        .login-button {
            display: inline-block;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
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
        .steps {
            margin: 30px 0;
        }
        .step {
            display: flex;
            align-items: flex-start;
            margin: 20px 0;
        }
        .step-number {
            background: #3b82f6;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .step-content {
            flex: 1;
        }
        .step-title {
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 5px;
        }
        .step-description {
            color: #6b7280;
            font-size: 14px;
        }
        .warning-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
        }
        .warning-title {
            color: #92400e;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .warning-text {
            color: #92400e;
            font-size: 14px;
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
            <div class="logo">X</div>
            <h1 class="title">Selamat Datang di XpressPOS!</h1>
            <p class="subtitle">Akun Anda sudah aktif dan siap digunakan</p>
        </div>

        <!-- Welcome Message -->
        <p>Halo <strong>{{ $user->name }}</strong>,</p>
        
        <p>Terima kasih telah berlangganan <strong>XpressPOS {{ $planName }}</strong>! Pembayaran Anda telah berhasil diproses dan akun bisnis <strong>{{ $businessName }}</strong> sudah aktif.</p>

        <!-- Login Credentials -->
        <div class="credentials-box">
            <div class="credentials-title">🔑 Informasi Login Anda</div>
            
            <div class="credential-item">
                <span class="credential-label">Email Login:</span>
                <div class="credential-value">{{ $user->email }}</div>
            </div>
            
            <div class="credential-item">
                <span class="credential-label">Password Sementara:</span>
                <div class="credential-value">{{ $temporaryPassword }}</div>
            </div>
            
            <div class="credential-item">
                <span class="credential-label">URL Dashboard:</span>
                <div class="credential-value">{{ $loginUrl }}</div>
            </div>
        </div>

        <!-- Security Warning -->
        <div class="warning-box">
            <div class="warning-title">⚠️ Penting untuk Keamanan</div>
            <div class="warning-text">
                Segera ganti password sementara Anda setelah login pertama kali. Password ini hanya berlaku untuk login pertama.
            </div>
        </div>

        <!-- Login Button -->
        <div style="text-align: center;">
            <a href="{{ $loginUrl }}" class="login-button">
                🚀 Masuk ke Dashboard Owner
            </a>
        </div>

        <!-- Next Steps -->
        <div class="steps">
            <h3 style="color: #1f2937; margin-bottom: 20px;">📋 Langkah Selanjutnya:</h3>
            
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <div class="step-title">Login & Ganti Password</div>
                    <div class="step-description">Gunakan email dan password sementara di atas untuk login, lalu ganti password Anda</div>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <div class="step-title">Setup Profil Toko</div>
                    <div class="step-description">Lengkapi informasi toko, alamat, dan pengaturan dasar bisnis Anda</div>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <div class="step-title">Tambah Produk Pertama</div>
                    <div class="step-description">Mulai menambahkan produk atau layanan yang Anda jual</div>
                </div>
            </div>
            
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-content">
                    <div class="step-title">Mulai Berjualan</div>
                    <div class="step-description">Sistem POS Anda siap digunakan untuk melayani pelanggan</div>
                </div>
            </div>
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

        <!-- Subscription Details -->
        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h4 style="color: #374151; margin-bottom: 15px;">📊 Detail Langganan Anda:</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; font-size: 14px;">
                <div><strong>Paket:</strong> XpressPOS {{ $planName }}</div>
                <div><strong>Bisnis:</strong> {{ $businessName }}</div>
                <div><strong>Email:</strong> {{ $user->email }}</div>
                <div><strong>Telepon:</strong> {{ $user->phone }}</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Email ini dikirim otomatis oleh sistem XpressPOS.<br>
            Jika Anda tidak melakukan pendaftaran ini, silakan hubungi support kami.</p>
            
            <p style="margin-top: 15px;">
                <strong>XpressPOS</strong> - Maksimalkan Bisnismu<br>
                © {{ date('Y') }} XpressPOS. All rights reserved.
            </p>
        </div>
    </div>
</body>
</html>