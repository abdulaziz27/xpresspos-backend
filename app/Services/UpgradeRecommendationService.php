<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Plan;

class UpgradeRecommendationService
{
    /**
     * Get upgrade recommendation for a store based on usage patterns.
     */
    public function getUpgradeRecommendation(Store $store): array
    {
        $subscription = $store->activeSubscription;
        
        if (!$subscription) {
            return [
                'recommended' => false,
                'reason' => 'no_subscription',
                'message' => 'Store has no active subscription',
            ];
        }
        
        $currentPlan = $subscription->plan;
        $usageAnalysis = $this->analyzeUsagePatterns($store);
        
        // Determine if upgrade is recommended
        $recommendation = $this->determineUpgradeRecommendation($currentPlan, $usageAnalysis);
        
        if (!$recommendation['recommended']) {
            return array_merge($recommendation, [
                'current_plan' => [
                    'name' => $currentPlan->name,
                    'slug' => $currentPlan->slug,
                    'price' => $currentPlan->price,
                    'limits' => $currentPlan->limits,
                    'features' => $currentPlan->features,
                ],
                'usage_analysis' => $usageAnalysis,
            ]);
        }
        
        // Get recommended plan
        $recommendedPlan = $this->getRecommendedPlan($currentPlan, $usageAnalysis);
        
        return array_merge($recommendation, [
            'current_plan' => [
                'name' => $currentPlan->name,
                'slug' => $currentPlan->slug,
                'price' => $currentPlan->price,
                'limits' => $currentPlan->limits,
                'features' => $currentPlan->features,
            ],
            'recommended_plan' => $recommendedPlan,
            'usage_analysis' => $usageAnalysis,
            'benefits' => $this->getUpgradeBenefits($currentPlan, $recommendedPlan),
            'urgency' => $this->calculateUrgency($usageAnalysis),
            'estimated_savings' => $this->calculateEstimatedSavings($currentPlan, $recommendedPlan),
        ]);
    }
    
    /**
     * Analyze usage patterns for a store.
     */
    private function analyzeUsagePatterns(Store $store): array
    {
        $subscription = $store->activeSubscription;
        $plan = $subscription->plan;
        
        $analysis = [
            'features' => [],
            'limits' => [],
            'overall_score' => 0,
        ];
        
        // Analyze feature usage
        $features = ['products', 'users', 'outlets', 'transactions'];
        
        foreach ($features as $feature) {
            $limit = $plan->getLimit($feature);
            $currentUsage = $store->getCurrentUsage($feature);
            
            $featureAnalysis = [
                'feature' => $feature,
                'current_usage' => $currentUsage,
                'limit' => $limit,
                'unlimited' => $limit === null,
                'usage_percentage' => $limit ? ($currentUsage / $limit) * 100 : 0,
                'status' => 'within_limits',
                'growth_trend' => $this->calculateGrowthTrend($store, $feature),
            ];
            
            if ($limit && $currentUsage >= $limit) {
                $featureAnalysis['status'] = 'exceeded';
                $analysis['overall_score'] += 100; // High impact
            } elseif ($limit && ($currentUsage / $limit) >= 0.8) {
                $featureAnalysis['status'] = 'approaching_limit';
                $analysis['overall_score'] += 60; // Medium impact
            } elseif ($limit && ($currentUsage / $limit) >= 0.6) {
                $featureAnalysis['status'] = 'moderate_usage';
                $analysis['overall_score'] += 20; // Low impact
            }
            
            $analysis['features'][$feature] = $featureAnalysis;
        }
        
        // Special handling for transaction quota
        $transactionUsage = $subscription->usage()->where('feature_type', 'transactions')->first();
        if ($transactionUsage && $transactionUsage->annual_quota) {
            $analysis['transaction_quota'] = [
                'current_usage' => $transactionUsage->current_usage,
                'annual_quota' => $transactionUsage->annual_quota,
                'usage_percentage' => $transactionUsage->getUsagePercentage(),
                'quota_exceeded' => $transactionUsage->hasExceededQuota(),
                'soft_cap_triggered' => $transactionUsage->soft_cap_triggered,
                'months_remaining' => $this->getMonthsRemainingInYear(),
                'projected_usage' => $this->projectAnnualUsage($transactionUsage),
            ];
            
            if ($transactionUsage->hasExceededQuota()) {
                $analysis['overall_score'] += 150; // Very high impact
            } elseif ($transactionUsage->getUsagePercentage() >= 80) {
                $analysis['overall_score'] += 80; // High impact
            }
        }
        
        return $analysis;
    }
    
    /**
     * Determine if upgrade is recommended based on usage analysis.
     */
    private function determineUpgradeRecommendation(Plan $currentPlan, array $usageAnalysis): array
    {
        $score = $usageAnalysis['overall_score'];
        
        // High urgency - immediate upgrade recommended
        if ($score >= 100) {
            return [
                'recommended' => true,
                'reason' => 'limits_exceeded',
                'message' => 'Immediate upgrade recommended - you have exceeded plan limits',
                'urgency' => 'high',
            ];
        }
        
        // Medium urgency - upgrade recommended soon
        if ($score >= 60) {
            return [
                'recommended' => true,
                'reason' => 'approaching_limits',
                'message' => 'Upgrade recommended - you are approaching plan limits',
                'urgency' => 'medium',
            ];
        }
        
        // Low urgency - consider upgrade for growth
        if ($score >= 40) {
            return [
                'recommended' => true,
                'reason' => 'growth_planning',
                'message' => 'Consider upgrading for continued growth and additional features',
                'urgency' => 'low',
            ];
        }
        
        // No upgrade needed
        return [
            'recommended' => false,
            'reason' => 'within_limits',
            'message' => 'Current plan meets your needs',
            'urgency' => 'none',
        ];
    }
    
    /**
     * Get recommended plan based on current plan and usage.
     */
    private function getRecommendedPlan(Plan $currentPlan, array $usageAnalysis): array
    {
        // Simple upgrade path
        $upgradePath = [
            'basic' => 'pro',
            'pro' => 'enterprise',
        ];
        
        $recommendedSlug = $upgradePath[$currentPlan->slug] ?? 'enterprise';
        $recommendedPlan = Plan::where('slug', $recommendedSlug)->first();
        
        if (!$recommendedPlan) {
            // Fallback to Enterprise
            $recommendedPlan = Plan::where('slug', 'enterprise')->first();
        }
        
        return [
            'name' => $recommendedPlan->name,
            'slug' => $recommendedPlan->slug,
            'price' => $recommendedPlan->price,
            'annual_price' => $recommendedPlan->annual_price,
            'limits' => $recommendedPlan->limits,
            'features' => $recommendedPlan->features,
            'description' => $recommendedPlan->description,
        ];
    }
    
    /**
     * Get upgrade benefits.
     */
    private function getUpgradeBenefits(Plan $currentPlan, array $recommendedPlan): array
    {
        $benefits = [];
        
        // Compare limits
        $currentLimits = $currentPlan->limits ?? [];
        $recommendedLimits = $recommendedPlan['limits'] ?? [];
        
        foreach ($recommendedLimits as $feature => $newLimit) {
            $currentLimit = $currentLimits[$feature] ?? 0;
            
            if ($newLimit === null) {
                $benefits[] = [
                    'type' => 'unlimited',
                    'feature' => $feature,
                    'description' => "Unlimited {$feature}",
                ];
            } elseif ($newLimit > $currentLimit) {
                $increase = $newLimit - $currentLimit;
                $benefits[] = [
                    'type' => 'increased_limit',
                    'feature' => $feature,
                    'description' => "Increase {$feature} limit by {$increase} (from {$currentLimit} to {$newLimit})",
                    'current_limit' => $currentLimit,
                    'new_limit' => $newLimit,
                    'increase' => $increase,
                ];
            }
        }
        
        // Compare features
        $currentFeatures = $currentPlan->features ?? [];
        $recommendedFeatures = $recommendedPlan['features'] ?? [];
        
        $newFeatures = array_diff($recommendedFeatures, $currentFeatures);
        foreach ($newFeatures as $feature) {
            $benefits[] = [
                'type' => 'new_feature',
                'feature' => $feature,
                'description' => "Access to " . str_replace('_', ' ', $feature),
            ];
        }
        
        return $benefits;
    }
    
    /**
     * Calculate urgency level.
     */
    private function calculateUrgency(array $usageAnalysis): string
    {
        $score = $usageAnalysis['overall_score'];
        
        if ($score >= 100) return 'high';
        if ($score >= 60) return 'medium';
        if ($score >= 40) return 'low';
        
        return 'none';
    }
    
    /**
     * Calculate estimated savings or costs.
     */
    private function calculateEstimatedSavings(Plan $currentPlan, array $recommendedPlan): array
    {
        $currentMonthly = $currentPlan->price;
        $recommendedMonthly = $recommendedPlan['price'];
        $recommendedAnnual = $recommendedPlan['annual_price'];
        
        $monthlyCostIncrease = $recommendedMonthly - $currentMonthly;
        $annualCostIncrease = ($recommendedAnnual ?? ($recommendedMonthly * 12)) - ($currentPlan->annual_price ?? ($currentMonthly * 12));
        
        // Calculate potential savings from annual billing
        $annualSavings = ($recommendedMonthly * 12) - ($recommendedAnnual ?? ($recommendedMonthly * 12));
        
        return [
            'monthly_cost_increase' => $monthlyCostIncrease,
            'annual_cost_increase' => $annualCostIncrease,
            'annual_billing_savings' => $annualSavings,
            'break_even_months' => $annualSavings > 0 ? ceil($annualSavings / $monthlyCostIncrease) : null,
        ];
    }
    
    /**
     * Calculate growth trend for a feature.
     */
    private function calculateGrowthTrend(Store $store, string $feature): string
    {
        // For now, return static trend
        // In production, this would analyze historical data
        return 'stable';
    }
    
    /**
     * Get months remaining in subscription year.
     */
    private function getMonthsRemainingInYear(): int
    {
        return now()->diffInMonths(now()->endOfYear());
    }
    
    /**
     * Project annual usage based on current usage.
     */
    private function projectAnnualUsage($transactionUsage): int
    {
        $monthsElapsed = now()->diffInMonths($transactionUsage->subscription_year_start) + 1;
        $monthsRemaining = 12 - $monthsElapsed;
        
        if ($monthsRemaining <= 0) {
            return $transactionUsage->current_usage;
        }
        
        $averageMonthlyUsage = $transactionUsage->current_usage / $monthsElapsed;
        $projectedTotal = $transactionUsage->current_usage + ($averageMonthlyUsage * $monthsRemaining);
        
        return (int) $projectedTotal;
    }
}