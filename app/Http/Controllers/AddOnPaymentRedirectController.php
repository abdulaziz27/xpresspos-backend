<?php

namespace App\Http\Controllers;

use App\Models\TenantAddOn;
use App\Services\XenditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AddOnPaymentRedirectController extends Controller
{
    public function __invoke(Request $request, XenditService $xenditService): RedirectResponse
    {
        $addonId = $request->query('addon_id');

        if (blank($addonId)) {
            return redirect('/owner')->with('warning', 'Add-on ID tidak ditemukan pada URL.');
        }

        $tenantAddOn = TenantAddOn::with(['latestPayment', 'addOn'])->find($addonId);

        if (! $tenantAddOn) {
            return redirect('/owner')->with('warning', 'Add-on yang dimaksud tidak ditemukan.');
        }

        $payment = $tenantAddOn->latestPayment ?? $tenantAddOn->payments()->latest()->first();

        if (! $payment) {
            return redirect('/owner')->with('warning', 'Tidak ada catatan pembayaran untuk add-on ini.');
        }

        if ($payment->isPaid()) {
            $this->activateTenantAddOn($tenantAddOn);

            return redirect('/owner')->with('success', 'Pembayaran add-on telah dikonfirmasi.');
        }

        try {
            $invoiceResponse = $xenditService->getInvoice($payment->xendit_invoice_id);
            $status = strtoupper(data_get($invoiceResponse, 'data.status', data_get($invoiceResponse, 'status', 'PENDING')));

            if ($status === 'PAID') {
                $payment->markAsPaid();
                $this->activateTenantAddOn($tenantAddOn);

                return redirect('/owner')->with('success', 'Pembayaran add-on berhasil dikonfirmasi.');
            }

            if (in_array($status, ['EXPIRED', 'FAILED'], true)) {
                $tenantAddOn->update(['status' => 'expired']);
                $payment->update(['status' => strtolower($status)]);

                return redirect('/owner')->with('warning', 'Pembayaran add-on gagal atau kedaluwarsa.');
            }
        } catch (\Throwable $e) {
            Log::error('Gagal memverifikasi pembayaran add-on', [
                'tenant_add_on_id' => $tenantAddOn->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return redirect('/owner')->with(
                'warning',
                'Pembayaran berhasil, menunggu konfirmasi otomatis dari sistem. Status akan diperbarui segera.'
            );
        }

        return redirect('/owner')->with(
            'info',
            'Pembayaran add-on masih diproses oleh Xendit. Silakan periksa kembali beberapa saat lagi.'
        );
    }

    protected function activateTenantAddOn(TenantAddOn $tenantAddOn): void
    {
        $tenantAddOn->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $tenantAddOn->billing_cycle === 'annual' ? now()->addYear() : null,
        ]);
    }
}

