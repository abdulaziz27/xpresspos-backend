# Tenant Entity Tables

## Tenant & Access
| Table | Fields |
| --- | --- |
| `tenants` | `id`, `name`, `email`, `phone`, `settings`, `status`, `created_at`, `updated_at` |
| `stores` | `id`, `tenant_id`, `name`, `code`, `email`, `phone`, `address`, `logo`, `timezone`, `currency`, `settings`, `status`, `created_at`, `updated_at` |
| `users` | `id`, `store_id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at` |
| `user_tenant_access` | `id`, `user_id`, `tenant_id`, `role`, `created_at`, `updated_at` |
| `store_user_assignments` | `id`, `store_id`, `user_id`, `assignment_role`, `is_primary`, `created_at`, `updated_at` |

## Product Catalog
| Table | Fields |
| --- | --- |
| `categories` | `id`, `store_id`, `name`, `slug`, `description`, `image`, `status`, `sort_order`, `created_at`, `updated_at` |
| `products` | `id`, `store_id`, `category_id`, `name`, `sku`, `description`, `image`, `price`, `cost_price`, `track_inventory`, `stock`, `min_stock_level`, `status`, `is_favorite`, `sort_order`, `created_at`, `updated_at` |
| `product_variants` | `id`, `store_id`, `product_id`, `name`, `value`, `price_adjustment`, `is_active`, `sort_order`, `created_at`, `updated_at` |
| `product_price_histories` | `id`, `store_id`, `product_id`, `old_price`, `new_price`, `old_cost_price`, `new_cost_price`, `changed_by`, `reason`, `effective_date`, `created_at`, `updated_at` |

### Recipes & Components
| Table | Fields |
| --- | --- |
| `recipes` | `id`, `store_id`, `product_id`, `name`, `description`, `yield_quantity`, `yield_unit`, `total_cost`, `cost_per_unit`, `is_active`, `created_at`, `updated_at` |
| `recipe_items` | `id`, `store_id`, `recipe_id`, `ingredient_product_id`, `quantity`, `unit`, `unit_cost`, `total_cost`, `notes`, `created_at`, `updated_at` |
| `modifier_recipe_items` | `id`, `modifier_item_id`, `inventory_item_id`, `uom_id`, `quantity`, `created_at`, `updated_at` |

### Modifiers
| Table | Fields |
| --- | --- |
| `modifier_groups` | `id`, `store_id`, `name`, `description`, `min_select`, `max_select`, `is_required`, `is_active`, `sort_order`, `created_at`, `updated_at` |
| `modifier_items` | `id`, `modifier_group_id`, `store_id`, `name`, `description`, `price_delta`, `is_active`, `sort_order`, `created_at`, `updated_at` |
| `product_modifier_groups` | `id`, `product_id`, `modifier_group_id`, `is_required`, `sort_order`, `created_at`, `updated_at` |
| `order_item_modifiers` | `id`, `order_item_id`, `modifier_item_id`, `quantity`, `price_delta`, `created_at`, `updated_at` |

## Inventory & Supply
| Table | Fields |
| --- | --- |
| `inventory_items` | `id`, `store_id`, `name`, `sku`, `category`, `uom_id`, `track_lot`, `track_stock`, `min_stock_level`, `default_cost`, `status`, `created_at`, `updated_at` |
| `stock_levels` | `id`, `store_id`, `product_id`, `current_stock`, `reserved_stock`, `available_stock`, `min_stock_level`, `average_cost`, `total_value`, `last_movement_at`, `created_at`, `updated_at` |
| `inventory_movements` | `id`, `store_id`, `product_id`, `user_id`, `type`, `quantity`, `unit_cost`, `total_cost`, `reason`, `reference_type`, `reference_id`, `notes`, `created_at`, `updated_at` |
| `cogs_history` | `id`, `store_id`, `product_id`, `order_id`, `quantity_sold`, `unit_cost`, `total_cogs`, `calculation_method`, `cost_breakdown`, `created_at`, `updated_at` |
| `cogs_details` | `id`, `cogs_history_id`, `order_item_id`, `inventory_item_id`, `lot_id`, `quantity`, `unit_cost`, `total_cost`, `created_at`, `updated_at` |

### Lots, Adjustments & Transfers
| Table | Fields |
| --- | --- |
| `inventory_lots` | `id`, `store_id`, `inventory_item_id`, `lot_code`, `mfg_date`, `exp_date`, `initial_qty`, `remaining_qty`, `unit_cost`, `status`, `created_at`, `updated_at` |
| `inventory_adjustments` | `id`, `store_id`, `user_id`, `adjustment_number`, `status`, `reason`, `adjusted_at`, `notes`, `created_at`, `updated_at` |
| `inventory_adjustment_items` | `id`, `inventory_adjustment_id`, `inventory_item_id`, `system_qty`, `counted_qty`, `difference_qty`, `unit_cost`, `total_cost`, `created_at`, `updated_at` |
| `inventory_transfers` | `id`, `from_store_id`, `to_store_id`, `transfer_number`, `status`, `shipped_at`, `received_at`, `notes`, `created_at`, `updated_at` |
| `inventory_transfer_items` | `id`, `inventory_transfer_id`, `inventory_item_id`, `uom_id`, `quantity_shipped`, `quantity_received`, `unit_cost`, `created_at`, `updated_at` |

### Procurement & Suppliers
| Table | Fields |
| --- | --- |
| `suppliers` | `id`, `tenant_id`, `store_id`, `name`, `email`, `phone`, `address`, `tax_id`, `bank_account`, `status`, `metadata`, `created_at`, `updated_at` |
| `purchase_orders` | `id`, `store_id`, `supplier_id`, `po_number`, `status`, `ordered_at`, `received_at`, `total_amount`, `notes`, `created_at`, `updated_at` |
| `purchase_order_items` | `id`, `purchase_order_id`, `inventory_item_id`, `uom_id`, `quantity_ordered`, `quantity_received`, `unit_cost`, `total_cost`, `created_at`, `updated_at` |

### Units & Conversions
| Table | Fields |
| --- | --- |
| `uoms` | `id`, `code`, `name`, `description`, `created_at`, `updated_at` |
| `uom_conversions` | `id`, `from_uom_id`, `to_uom_id`, `multiplier`, `created_at`, `updated_at` |

## Orders & Dining
| Table | Fields |
| --- | --- |
| `orders` | `id`, `store_id`, `user_id`, `member_id`, `customer_name`, `customer_type`, `operation_mode`, `payment_mode`, `table_id`, `order_number`, `status`, `subtotal`, `tax_amount`, `discount_amount`, `service_charge`, `total_amount`, `currency`, `notes`, `completed_at`, `created_at`, `updated_at` |
| `order_items` | `id`, `store_id`, `order_id`, `product_id`, `product_name`, `product_sku`, `quantity`, `unit_price`, `total_price`, `product_options`, `notes`, `created_at`, `updated_at` |
| `order_discounts` | `id`, `order_id`, `promotion_id`, `voucher_id`, `discount_type`, `discount_amount`, `created_at`, `updated_at` |
| `order_item_discounts` | `id`, `order_item_id`, `promotion_id`, `discount_type`, `discount_amount`, `created_at`, `updated_at` |
| `tables` | `id`, `store_id`, `table_number`, `name`, `capacity`, `status`, `location`, `description`, `qr_code`, `is_active`, `occupied_at`, `last_cleared_at`, `current_order_id`, `total_occupancy_count`, `average_occupancy_duration`, `notes`, `created_at`, `updated_at` |
| `table_occupancy_histories` | `id`, `store_id`, `table_id`, `order_id`, `user_id`, `occupied_at`, `cleared_at`, `duration_minutes`, `party_size`, `order_total`, `status`, `notes`, `metadata`, `created_at`, `updated_at` |
| `discounts` | `id`, `store_id`, `name`, `description`, `type`, `value`, `status`, `expired_date`, `created_at`, `updated_at` |

## Payments & Financials
| Table | Fields |
| --- | --- |
| `payment_methods` | `id`, `user_id`, `gateway`, `gateway_id`, `type`, `last_four`, `expires_at`, `is_default`, `metadata`, `created_at`, `updated_at` |
| `payments` | `id`, `store_id`, `order_id`, `payment_method`, `gateway`, `gateway_transaction_id`, `payment_method_id`, `invoice_id`, `gateway_fee`, `gateway_response`, `amount`, `reference_number`, `status`, `processed_at`, `notes`, `created_at`, `updated_at` |
| `refunds` | `id`, `store_id`, `order_id`, `payment_id`, `user_id`, `amount`, `reason`, `status`, `approved_by`, `approved_at`, `processed_at`, `notes`, `created_at`, `updated_at` |
| `invoices` | `id`, `subscription_id`, `invoice_number`, `amount`, `tax_amount`, `total_amount`, `status`, `due_date`, `paid_at`, `line_items`, `metadata`, `created_at`, `updated_at` |
| `cash_sessions` | `id`, `store_id`, `user_id`, `opening_balance`, `closing_balance`, `expected_balance`, `cash_sales`, `cash_expenses`, `variance`, `status`, `opened_at`, `closed_at`, `notes`, `created_at`, `updated_at` |
| `expenses` | `id`, `store_id`, `cash_session_id`, `user_id`, `category`, `description`, `amount`, `receipt_number`, `vendor`, `expense_date`, `notes`, `created_at`, `updated_at` |
| `payment_security_logs` | `id`, `event`, `level`, `ip_address`, `user_agent`, `url`, `method`, `user_id`, `user_email`, `context`, `headers`, `created_at`, `updated_at` |
| `payment_audit_logs` | `id`, `operation`, `entity_type`, `entity_id`, `user_id`, `user_email`, `ip_address`, `user_agent`, `old_data`, `new_data`, `changes`, `request_id`, `session_id`, `created_at` |
| `api_keys` | `id`, `provider`, `environment`, `store_id`, `encrypted_key`, `key_hash`, `is_active`, `rotation_count`, `expires_at`, `deactivated_at`, `deactivation_reason`, `created_at`, `updated_at` |

## Loyalty & Membership
| Table | Fields |
| --- | --- |
| `member_tiers` | `id`, `store_id`, `name`, `slug`, `min_points`, `max_points`, `discount_percentage`, `benefits`, `color`, `sort_order`, `is_active`, `description`, `created_at`, `updated_at` |
| `members` | `id`, `store_id`, `member_number`, `name`, `email`, `phone`, `date_of_birth`, `address`, `loyalty_points`, `total_spent`, `visit_count`, `last_visit_at`, `tier_id`, `is_active`, `notes`, `created_at`, `updated_at` |
| `loyalty_point_transactions` | `id`, `store_id`, `member_id`, `order_id`, `user_id`, `type`, `points`, `balance_before`, `balance_after`, `reason`, `description`, `metadata`, `expires_at`, `created_at`, `updated_at` |

## Promotions & Vouchers
| Table | Fields |
| --- | --- |
| `promotions` | `id`, `tenant_id`, `store_id`, `name`, `description`, `type`, `code`, `stackable`, `status`, `starts_at`, `ends_at`, `priority`, `created_at`, `updated_at` |
| `promotion_conditions` | `id`, `promotion_id`, `condition_type`, `condition_value`, `created_at`, `updated_at` |
| `promotion_rewards` | `id`, `promotion_id`, `reward_type`, `reward_value`, `created_at`, `updated_at` |
| `vouchers` | `id`, `tenant_id`, `promotion_id`, `code`, `max_redemptions`, `redemptions_count`, `valid_from`, `valid_until`, `status`, `created_at`, `updated_at` |
| `voucher_redemptions` | `id`, `voucher_id`, `member_id`, `order_id`, `redeemed_at`, `discount_amount`, `created_at`, `updated_at` |

## Subscriptions & Billing
| Table | Fields |
| --- | --- |
| `plans` | `id`, `name`, `slug`, `description`, `price`, `annual_price`, `features`, `limits`, `is_active`, `sort_order`, `created_at`, `updated_at` |
| `plan_features` | `id`, `plan_id`, `feature_code`, `limit_value`, `is_enabled`, `created_at`, `updated_at` |
| `subscriptions` | `id`, `tenant_id`, `plan_id`, `status`, `billing_cycle`, `starts_at`, `ends_at`, `trial_ends_at`, `amount`, `metadata`, `created_at`, `updated_at` |
| `subscription_usage` | `id`, `subscription_id`, `feature_type`, `current_usage`, `annual_quota`, `subscription_year_start`, `subscription_year_end`, `soft_cap_triggered`, `soft_cap_triggered_at`, `created_at`, `updated_at` |
| `landing_subscriptions` | `id`, `user_id`, `tenant_id`, `email`, `name`, `company`, `phone`, `country`, `preferred_contact_method`, `notes`, `follow_up_logs`, `plan`, `plan_id`, `billing_cycle`, `status`, `stage`, `xendit_invoice_id`, `payment_status`, `payment_amount`, `paid_at`, `subscription_id`, `meta`, `processed_at`, `processed_by`, `provisioned_store_id`, `provisioned_user_id`, `provisioned_at`, `onboarding_url`, `business_name`, `business_type`, `is_upgrade`, `is_downgrade`, `previous_plan_id`, `created_at`, `updated_at` |
| `subscription_payments` | `id`, `landing_subscription_id`, `subscription_id`, `invoice_id`, `xendit_invoice_id`, `external_id`, `payment_method`, `payment_channel`, `amount`, `gateway_fee`, `status`, `gateway_response`, `paid_at`, `expires_at`, `created_at`, `updated_at` |

## Operations & Audit
| Table | Fields |
| --- | --- |
| `activity_logs` | `id`, `store_id`, `user_id`, `event`, `auditable_type`, `auditable_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`, `updated_at` |
| `staff_invitations` | `id`, `store_id`, `invited_by`, `email`, `name`, `role`, `token`, `status`, `expires_at`, `accepted_at`, `user_id`, `metadata`, `created_at`, `updated_at` |
| `staff_performances` | `id`, `store_id`, `user_id`, `date`, `orders_processed`, `total_sales`, `average_order_value`, `refunds_processed`, `refund_amount`, `hours_worked`, `sales_per_hour`, `customer_interactions`, `customer_satisfaction_score`, `additional_metrics`, `created_at`, `updated_at` |
| `sync_operations` | `id`, `store_id`, `user_id`, `batch_id`, `idempotency_key`, `sync_type`, `operation`, `entity_type`, `entity_id`, `payload`, `conflicts`, `status`, `priority`, `error_message`, `retry_count`, `scheduled_at`, `started_at`, `last_retry_at`, `completed_at`, `created_at`, `updated_at` |
| `permission_audit_logs` | `id`, `store_id`, `user_id`, `changed_by`, `action`, `permission`, `old_value`, `new_value`, `notes`, `created_at`, `updated_at` |

### Authorization (Spatie Permission Tables)
| Table | Fields |
| --- | --- |
| `permissions` | `id`, `name`, `guard_name`, `created_at`, `updated_at` |
| `roles` | `id`, `store_id`, `name`, `guard_name`, `created_at`, `updated_at` |
| `model_has_permissions` | `permission_id`, `model_type`, `model_id`, `store_id` |
| `model_has_roles` | `role_id`, `model_type`, `model_id`, `store_id` |
| `role_has_permissions` | `permission_id`, `role_id` |

> The list above consolidates every Laravel migration under `database/migrations` so future tenant-scoped refactors can trace which entities currently depend on `store_id` versus `tenant_id`.

