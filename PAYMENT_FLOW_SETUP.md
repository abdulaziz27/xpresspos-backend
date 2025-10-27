# XpressPOS Payment Flow Setup Guide

## 🚀 Quick Development Setup

### 1. Setup Development Environment
```bash
# Run the automated setup command
php artisan dev:setup

# Or manually copy and configure
cp .env.example .env
php artisan key:generate
```

### 2. Configure Database
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE xpresspos_dev;"

# Run migrations
php artisan migrate

# Seed with sample data
php artisan db:seed
```

### 3. Start Development Server
```bash
php artisan serve
```

## 📋 Payment Flow URLs

### Customer Flow
- **Landing Page**: `http://localhost:8000`
- **Pricing**: `http://localhost:8000/pricing`
- **Checkout**: `http://localhost:8000/checkout?plan=pro&billing=monthly`
- **Payment**: `http://localhost:8000/payment`
- **Success**: `http://localhost:8000/payment/success`
- **Failed**: `http://localhost:8000/payment/failed`
- **Customer Dashboard**: `http://localhost:8000/customer-dashboard?email=test@example.com`

### Admin Flow
- **Owner Dashboard**: `http://localhost:8000/owner-panel`
- **Payment Analytics**: `http://localhost:8000/owner-panel/subscription-payments`

## 🔧 Development Mode Features

### Automatic Dummy Mode
When `XENDIT_API_KEY` is not configured or set to dummy value:
- ✅ Payment flow works without real Xendit API
- ✅ Dummy invoices are created for testing
- ✅ Success page shows development notice
- ✅ All payment methods can be tested

### Environment Variables
```env
# Development Xendit Config
XENDIT_API_KEY=xnd_development_dummy_key_for_local_testing
XENDIT_WEBHOOK_TOKEN=dummy_webhook_token_for_local_testing
XENDIT_IS_PRODUCTION=false

# Payment Config
PAYMENT_CURRENCY=IDR
PAYMENT_EXPIRY_HOURS=24
```

## 🧪 Testing Payment Flow

### 1. Test Complete Flow
1. Visit `/pricing`
2. Select a plan (Basic/Pro/Enterprise)
3. Fill checkout form
4. Choose payment method
5. Complete payment (dummy mode)
6. View success page
7. Access customer dashboard

### 2. Test API Endpoints
```bash
# Create subscription payment
curl -X POST http://localhost:8000/api/v1/subscription-payments/create \
  -H "Content-Type: application/json" \
  -d '{
    "landing_subscription_id": 1,
    "amount": 599000,
    "payment_method": "bank_transfer"
  }'

# Check payment status
curl http://localhost:8000/api/v1/subscription-payments/status/{invoice_id}

# Get payment history
curl http://localhost:8000/api/v1/subscription-payments/history?landing_subscription_id=1
```

## 🔐 Production Setup

### 1. Xendit Configuration
```env
XENDIT_API_KEY=xnd_production_your_real_api_key
XENDIT_WEBHOOK_TOKEN=your_real_webhook_token
XENDIT_IS_PRODUCTION=true
```

### 2. Webhook URLs
Configure these URLs in your Xendit dashboard:
- **Invoice Callback**: `https://yourdomain.com/api/webhooks/xendit/invoice`
- **Recurring Callback**: `https://yourdomain.com/api/webhooks/xendit/recurring`

### 3. Security Features
- ✅ Webhook signature validation
- ✅ Rate limiting (60 requests/minute per IP)
- ✅ IP whitelist support
- ✅ Request size limits (1MB max)
- ✅ Replay attack protection
- ✅ Comprehensive audit logging
- ✅ Data encryption for sensitive fields

## 📊 Database Tables

### Core Tables
- `landing_subscriptions` - Customer subscription data
- `subscription_payments` - Payment records
- `payment_security_logs` - Security events
- `payment_audit_logs` - Audit trail
- `api_keys` - Encrypted API key storage

### Key Relationships
```
landing_subscriptions (1) -> (many) subscription_payments
subscription_payments (1) -> (1) subscriptions
subscriptions (1) -> (1) stores
stores (1) -> (many) users
```

## 🚨 Troubleshooting

### Common Issues

#### 1. "Cannot assign null to property XenditService::$apiKey"
**Solution**: Run `php artisan dev:setup` or set dummy API key in .env

#### 2. Payment flow not working
**Check**:
- Database migrations are run
- .env file has proper configuration
- Laravel server is running

#### 3. Webhook not receiving callbacks
**Check**:
- Webhook URL is accessible from internet
- Xendit dashboard has correct webhook URLs
- Security middleware is not blocking requests

### Debug Commands
```bash
# Check configuration
php artisan config:show xendit

# View logs
tail -f storage/logs/laravel.log

# Test database connection
php artisan migrate:status

# Clear cache
php artisan config:clear
php artisan cache:clear
```

## 📈 Monitoring & Analytics

### Security Monitoring
- Real-time security event logging
- Automatic threat detection
- IP blocking for suspicious activity
- Comprehensive audit trails

### Payment Analytics
- Payment success/failure rates
- Revenue tracking by plan
- Customer conversion metrics
- Payment method preferences

### Dashboard Widgets
- Payment method breakdown
- Subscription analytics
- Revenue charts
- Security alerts

## 🔄 Deployment Checklist

### Before Production
- [ ] Set real Xendit API keys
- [ ] Configure webhook URLs
- [ ] Set up SSL certificates
- [ ] Configure email settings
- [ ] Set up monitoring
- [ ] Test payment flow end-to-end
- [ ] Configure backup strategy
- [ ] Set up error tracking

### Security Checklist
- [ ] Enable webhook signature validation
- [ ] Configure IP whitelist (if needed)
- [ ] Set up rate limiting
- [ ] Enable audit logging
- [ ] Configure data encryption
- [ ] Set up security monitoring
- [ ] Test security features

---

## 📞 Support

For technical support or questions:
- **Email**: dev@xpresspos.id
- **Documentation**: Check inline code comments
- **Logs**: `storage/logs/laravel.log`