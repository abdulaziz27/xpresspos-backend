<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Member;
use App\Models\Customer;

class CustomerResolutionService
{
    /**
     * Resolve customer information for order.
     */
    public function resolveCustomer(array $orderData, Store $store): array
    {
        $customerSettings = $store->settings['customer_settings'] ?? [];
        
        // Priority 1: If member_id provided, use member
        if (!empty($orderData['member_id'])) {
            return $this->resolveMemberCustomer($orderData, $store);
        }
        
        // Priority 2: If customer_name provided, create/find guest
        if (!empty($orderData['customer_name'])) {
            return $this->resolveGuestCustomer($orderData, $store);
        }
        
        // Priority 3: Use default customer
        return $this->resolveDefaultCustomer($store, $customerSettings);
    }
    
    /**
     * Resolve member customer.
     */
    private function resolveMemberCustomer(array $orderData, Store $store): array
    {
        $member = Member::where('store_id', $store->id)
            ->where('id', $orderData['member_id'])
            ->first();
            
        if (!$member) {
            throw new \InvalidArgumentException('Member not found');
        }
        
        return [
            'customer_id' => $member->id,
            'customer_name' => $orderData['customer_name'] ?? $member->name,
            'customer_type' => 'member',
            'customer_phone' => $member->phone,
            'customer_email' => $member->email,
        ];
    }
    
    /**
     * Resolve guest customer.
     */
    private function resolveGuestCustomer(array $orderData, Store $store): array
    {
        $customerName = trim($orderData['customer_name']);
        $customerPhone = $orderData['customer_phone'] ?? null;
        
        // Try to find existing guest by name and phone
        $existingGuest = null;
        if ($customerPhone) {
            $existingGuest = Member::where('store_id', $store->id)
                ->where('phone', $customerPhone)
                ->where('name', $customerName)
                ->first();
        }
        
        if ($existingGuest) {
            return [
                'customer_id' => $existingGuest->id,
                'customer_name' => $customerName,
                'customer_type' => 'guest',
                'customer_phone' => $customerPhone,
                'customer_email' => $orderData['customer_email'] ?? null,
            ];
        }
        
        // Create new guest customer if auto_create_guest enabled
        $autoCreateGuest = $store->settings['customer_settings']['auto_create_guest'] ?? true;
        
        if ($autoCreateGuest) {
            $guest = Member::create([
                'store_id' => $store->id,
                'member_number' => $this->generateGuestNumber($store),
                'name' => $customerName,
                'phone' => $customerPhone,
                'email' => $orderData['customer_email'] ?? null,
                'is_active' => true,
                'loyalty_points' => 0,
                'total_spent' => 0,
                'visit_count' => 0,
            ]);
            
            return [
                'customer_id' => $guest->id,
                'customer_name' => $customerName,
                'customer_type' => 'guest',
                'customer_phone' => $customerPhone,
                'customer_email' => $orderData['customer_email'] ?? null,
            ];
        }
        
        // If auto_create_guest disabled, use default customer
        return $this->resolveDefaultCustomer($store, $store->settings['customer_settings'] ?? []);
    }
    
    /**
     * Resolve default customer.
     */
    private function resolveDefaultCustomer(Store $store, array $customerSettings): array
    {
        $defaultName = $customerSettings['default_customer_name'] ?? 'Customer';
        $tenantId = $store->tenant_id;
        
        // Find or create default customer per tenant
        // Note: Unique constraint is on tenant_id + member_number, so we check by tenant_id
        $defaultCustomer = Member::firstOrCreate([
            'tenant_id' => $tenantId,
            'member_number' => 'DEFAULT',
        ], [
            'store_id' => $store->id,
            'name' => $defaultName,
            'is_active' => true,
            'loyalty_points' => 0,
            'total_spent' => 0,
            'visit_count' => 0,
        ]);
        
        return [
            'customer_id' => $defaultCustomer->id,
            'customer_name' => $defaultName,
            'customer_type' => 'walk_in',
            'customer_phone' => null,
            'customer_email' => null,
        ];
    }
    
    /**
     * Generate guest number.
     */
    private function generateGuestNumber(Store $store): string
    {
        $prefix = 'GUEST';
        $date = now()->format('Ymd');
        $sequence = Member::where('store_id', $store->id)
            ->where('member_number', 'like', $prefix . $date . '%')
            ->count() + 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get customer display name for receipt.
     */
    public function getReceiptCustomerName(array $customerData, Store $store): string
    {
        $showCustomerName = $store->settings['receipt_settings']['show_customer_name'] ?? true;
        
        if (!$showCustomerName) {
            return $store->settings['customer_settings']['default_customer_name'] ?? 'Customer';
        }
        
        return $customerData['customer_name'] ?? 'Customer';
    }
}