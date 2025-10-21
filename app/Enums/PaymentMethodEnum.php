<?php

namespace App\Enums;

enum PaymentMethodEnum: string
{
    case CASH = 'cash';
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD = 'debit_card';
    case QRIS = 'qris';
    case BANK_TRANSFER = 'bank_transfer';
    case E_WALLET = 'e_wallet';

    public function label(): string
    {
        return match($this) {
            self::CASH => 'Cash',
            self::CREDIT_CARD => 'Credit Card',
            self::DEBIT_CARD => 'Debit Card',
            self::QRIS => 'QRIS',
            self::BANK_TRANSFER => 'Bank Transfer',
            self::E_WALLET => 'E-Wallet',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::CASH => 'Cash payment',
            self::CREDIT_CARD => 'Credit card payment via EDC',
            self::DEBIT_CARD => 'Debit card payment via EDC',
            self::QRIS => 'QR Code payment',
            self::BANK_TRANSFER => 'Direct bank transfer',
            self::E_WALLET => 'Digital wallet payment',
        };
    }

    public function requiresReference(): bool
    {
        return match($this) {
            self::CASH => false,
            default => true,
        };
    }

    public function isCard(): bool
    {
        return in_array($this, [self::CREDIT_CARD, self::DEBIT_CARD]);
    }

    public function isDigital(): bool
    {
        return in_array($this, [self::QRIS, self::E_WALLET]);
    }

    public static function getAll(): array
    {
        return array_map(fn($case) => [
            'id' => $case->value,
            'name' => $case->label(),
            'description' => $case->description(),
            'requires_reference' => $case->requiresReference(),
            'is_active' => true,
        ], self::cases());
    }

    public static function getGrouped(): array
    {
        return [
            'cash' => [
                'label' => 'Cash Payment',
                'methods' => [self::CASH]
            ],
            'cards' => [
                'label' => 'Card Payments',
                'methods' => [self::CREDIT_CARD, self::DEBIT_CARD]
            ],
            'digital' => [
                'label' => 'Digital Payments',
                'methods' => [self::QRIS, self::E_WALLET, self::BANK_TRANSFER]
            ]
        ];
    }
}