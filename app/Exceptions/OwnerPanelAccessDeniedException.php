<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OwnerPanelAccessDeniedException extends AccessDeniedHttpException
{
    protected $reason;
    protected $userEmail;
    protected $storeId;
    protected $userRoles;

    public function __construct(
        string $reason,
        ?string $userEmail = null,
        ?string $storeId = null,
        array $userRoles = []
    ) {
        $this->reason = $reason;
        $this->userEmail = $userEmail;
        $this->storeId = $storeId;
        $this->userRoles = $userRoles;

        $message = $this->getDetailedMessage();
        parent::__construct($message);
    }

    protected function getDetailedMessage(): string
    {
        return match ($this->reason) {
            'not_authenticated' => 'Anda harus login terlebih dahulu untuk mengakses dashboard toko.',
            'no_store' => 'Akun Anda belum terhubung dengan toko. Silakan hubungi administrator untuk mengaktifkan akses ke dashboard toko.',
            'no_owner_role' => 'Anda tidak memiliki izin sebagai pemilik toko untuk mengakses dashboard ini. Pastikan akun Anda memiliki role "Owner" atau hubungi administrator untuk menetapkan role yang sesuai.',
            'no_owner_assignment' => 'Akun Anda belum ditetapkan sebagai pemilik toko. Silakan hubungi administrator untuk menetapkan akses ke toko Anda.',
            'store_inactive' => 'Toko yang terhubung dengan akun Anda saat ini tidak aktif. Silakan hubungi administrator untuk mengaktifkan toko.',
            'subscription_expired' => 'Langganan Anda telah berakhir. Silakan perpanjang langganan untuk melanjutkan akses ke dashboard.',
            'email_not_verified' => 'Email Anda belum diverifikasi. Silakan verifikasi email Anda terlebih dahulu sebelum mengakses dashboard. Periksa kotak masuk email Anda untuk link verifikasi.',
            default => 'Anda tidak memiliki izin untuk mengakses dashboard toko. Silakan hubungi administrator jika Anda yakin seharusnya memiliki akses.',
        };
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getUserEmail(): ?string
    {
        return $this->userEmail;
    }

    public function getStoreId(): ?string
    {
        return $this->storeId;
    }

    public function getUserRoles(): array
    {
        return $this->userRoles;
    }

    public function toArray(): array
    {
        return [
            'reason' => $this->reason,
            'message' => $this->getMessage(),
            'user_email' => $this->userEmail,
            'store_id' => $this->storeId,
            'user_roles' => $this->userRoles,
        ];
    }
}

