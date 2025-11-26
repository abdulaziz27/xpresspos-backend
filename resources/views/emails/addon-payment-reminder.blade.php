<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengingat Pembayaran Add-on</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f5f7fb;
            color: #101828;
            margin: 0;
            padding: 0;
        }
        .wrapper {
            max-width: 640px;
            margin: 0 auto;
            padding: 32px 16px;
        }
        .card {
            background: #ffffff;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }
        .title {
            font-size: 22px;
            font-weight: 600;
            margin: 0 0 16px;
        }
        .muted {
            color: #475467;
            line-height: 1.6;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin: 24px 0;
        }
        .stat {
            border: 1px solid #e4e7ec;
            border-radius: 10px;
            padding: 16px;
            background: #fafbff;
        }
        .stat-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #475467;
            margin-bottom: 6px;
        }
        .stat-value {
            font-size: 18px;
            font-weight: 600;
            color: #101828;
        }
        .btn {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 16px;
        }
        .footer {
            margin-top: 24px;
            font-size: 13px;
            color: #98a2b3;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <p class="muted">Halo {{ $user->name }},</p>
            <h1 class="title">Segera selesaikan pembayaran add-on Anda</h1>
            <p class="muted">
                Pembayaran untuk add-on <strong>{{ $addOn->name ?? 'Add-on XpressPOS' }}</strong> masih tertunda. 
                Mohon selesaikan sebelum <strong>{{ optional($expiresAt)->format('d M Y H:i') ?? 'tenggat yang ditentukan' }}</strong> 
                agar tambahan limit tetap aktif.
            </p>

            <div class="stat-grid">
                <div class="stat">
                    <div class="stat-label">NOMOR INVOICE</div>
                    <div class="stat-value">{{ $payment->xendit_invoice_id }}</div>
                </div>
                <div class="stat">
                    <div class="stat-label">TOTAL TAGIHAN</div>
                    <div class="stat-value">{{ $amountFormatted }}</div>
                </div>
                <div class="stat">
                    <div class="stat-label">SISA WAKTU</div>
                    <div class="stat-value">
                        @if(!is_null($hoursRemaining) && $hoursRemaining >= 0)
                            {{ $hoursRemaining }} jam lagi
                        @else
                            Segera kedaluwarsa
                        @endif
                    </div>
                </div>
            </div>

            @if(!empty($invoiceUrl))
                <a href="{{ $invoiceUrl }}" class="btn" target="_blank" rel="noopener">Bayar Sekarang</a>
            @endif

            <p class="muted" style="margin-top: 24px;">
                Jika Anda sudah melakukan pembayaran namun masih menerima email ini, harap abaikan atau hubungi tim support kami.
            </p>
        </div>

        <p class="footer">
            Email ini dikirim otomatis oleh XpressPOS. Jika Anda membutuhkan bantuan, hubungi support@xpresspos.id.
        </p>
    </div>
</body>
</html>

