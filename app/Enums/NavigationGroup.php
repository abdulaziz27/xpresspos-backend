<?php

namespace App\Enums;

enum NavigationGroup: string
{
    case SYSTEM_MANAGEMENT = 'System Management';
    case STORE_MANAGEMENT = 'Store Management';
    case USER_MANAGEMENT = 'User Management';
    case SUBSCRIPTION_MANAGEMENT = 'Subscription Management';
    case PRODUCT_MANAGEMENT = 'Product Management';
    case ORDER_MANAGEMENT = 'Order Management';
    case CUSTOMER_MANAGEMENT = 'Customer Management';
    case FINANCIAL_MANAGEMENT = 'Financial Management';
    case INVENTORY_MANAGEMENT = 'Inventory Management';
    case STORE_OPERATIONS = 'Store Operations';
}
