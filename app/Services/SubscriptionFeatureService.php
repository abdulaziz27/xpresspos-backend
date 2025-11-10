<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SubscriptionFeatureService
{
    /**
     * Feature definitions by tier
     */
    protected array $tierFeatures = [
        'free' => [
            'features' => [],
            'limits' => [
                'stores' => 1,
                'products' => 50,
                'staff' => 2,
                'orders_per_month' => 100,
            ]
        ],
        'basic' => [
            'features' => [
                'basic_reports',
                'inventory_management',
                'customer_management',
            ],
            'limits' => [
                'stores' => 1,
                'products' => 500,
                'staff' => 10,
                'orders_per_month' => 1000,
            ]
        ],
        'pro' => [
            'features' => [
                'basic_reports',
                'inventory_management',
                'customer_management',
                'advanced_analytics',
                'multi_store',
                'api_access',
                'custom_reports',
            ],
            'limits' => [
                'stores' => 3,
                'products' => 2000,
                'staff' => 50,
                'orders_per_month' => -1, // unlimited
            ]
        ],
        'enterprise' => [
            'features' => [
                'basic_reports',
                'inventory_management',
                'customer_management',
                'advanced_analytics',
                'multi_store',
                'api_access',
                'custom_reports',
                'priority_support',
                'custom_integrations',
                'white_label',
            ],
            'limits' => [
                'stores' => -1, // unlimited
                'products' => -1,
                'staff' => -1,
                'orders_per_month' => -1,
            ]
        ],
    ];

    /**
     * Check if feature is available for user
     */
    public function isFeatureAvailable(User $user, string $feature): bool
    {
        return $user->hasFeature($feature);
    }

    /**
     * Get upgrade message for locked feature
     */
    public function getUpgradeMessage(string $feature): string
    {
        $messages = [
            'advanced_analytics' => 'Upgrade to Pro to unlock Advanced Analytics',
            'multi_store' => 'Upgrade to Pro to manage multiple stores',
            'api_access' => 'Upgrade to Pro to access API features',
            'custom_reports' => 'Upgrade to Pro for custom reporting',
            'priority_support' => 'Upgrade to Enterprise for priority support',
        ];

        return $messages[$feature] ?? 'Upgrade your plan to unlock this feature';
    }

    /**
     * Get features comparison for upgrade prompt
     */
    public function getFeaturesComparison(): array
    {
        return [
            'Free' => [
                'price' => 0,
                'features' => [
                    '1 Store',
                    '50 Products',
                    '2 Staff Members',
                    'Basic POS',
                ],
            ],
            'Basic' => [
                'price' => 199000,
                'features' => [
                    '1 Store',
                    '500 Products',
                    '10 Staff Members',
                    'Basic Reports',
                    'Inventory Management',
                ],
            ],
            'Pro' => [
                'price' => 499000,
                'features' => [
                    '3 Stores',
                    '2000 Products',
                    '50 Staff Members',
                    'Advanced Analytics',
                    'API Access',
                    'Custom Reports',
                ],
                'popular' => true,
            ],
            'Enterprise' => [
                'price' => 'Custom',
                'features' => [
                    'Unlimited Everything',
                    'Priority Support',
                    'Custom Integrations',
                    'White Label',
                ],
            ],
        ];
    }

    /**
     * Check if user needs to upgrade
     */
    public function needsUpgrade(User $user, string $action): array
    {
        $result = [
            'needs_upgrade' => false,
            'message' => '',
            'current_tier' => $user->getSubscriptionTier(),
            'recommended_tier' => null,
        ];

        // Check based on action
        if ($action === 'create_store' && !$user->canCreate('stores')) {
            $result['needs_upgrade'] = true;
            $result['message'] = 'You have reached your store limit. Upgrade to Pro for multiple stores.';
            $result['recommended_tier'] = 'Pro';
        }

        if ($action === 'create_product' && !$user->canCreate('products')) {
            $result['needs_upgrade'] = true;
            $result['message'] = 'You have reached your product limit. Upgrade to increase your limit.';
            $result['recommended_tier'] = $user->isFreePlan() ? 'Basic' : 'Pro';
        }

        if ($action === 'add_staff' && !$user->canCreate('staff')) {
            $result['needs_upgrade'] = true;
            $result['message'] = 'You have reached your staff limit. Upgrade to add more team members.';
            $result['recommended_tier'] = $user->isFreePlan() ? 'Basic' : 'Pro';
        }

        return $result;
    }
}
