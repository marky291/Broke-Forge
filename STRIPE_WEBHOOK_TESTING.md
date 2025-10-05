# Stripe Webhook Testing Guide - Phase 6

This guide will help you test Stripe webhooks locally and configure them for production.

---

## 🎯 What Are Webhooks?

Webhooks allow Stripe to notify your application when events happen in your Stripe account (payments, subscriptions, etc.). This is essential for:
- Syncing subscription status
- Handling failed payments
- Recording billing events
- Updating user access

---

## 📋 Prerequisites

✅ Completed Phase 5 (Frontend implemented)
✅ Subscription plans seeded in database
✅ Dev server running (`composer dev`)

---

## 🛠️ Local Webhook Testing with Stripe CLI

### Step 1: Install Stripe CLI

**Windows:**
1. Download from: https://github.com/stripe/stripe-cli/releases/latest
2. Download `stripe_X.X.X_windows_x86_64.zip`
3. Extract to a folder (e.g., `C:\stripe\`)
4. Add to PATH or run from that directory

**Verify Installation:**
```bash
stripe --version
```

### Step 2: Login to Stripe CLI

```bash
stripe login
```

This will:
1. Open your browser
2. Ask you to authorize the CLI
3. Connect CLI to your Stripe account

### Step 3: Forward Webhooks to Local Server

Open a **new terminal** and run:

```bash
stripe listen --forward-to http://192.168.1.51:8080/stripe/webhook
```

You should see:
```
> Ready! Your webhook signing secret is whsec_xxxxxxxxxxxxx
```

**⚠️ IMPORTANT**: Copy this `whsec_xxxxxxxxxxxxx` webhook secret!

### Step 4: Update .env with Webhook Secret

In your `.env` file, update:

```env
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx  ← Use the secret from Step 3
```

**Restart your dev server** after updating:
```bash
# Stop composer dev (Ctrl+C)
composer dev
```

---

## 🧪 Testing Webhooks

### Test 1: Trigger a Test Event

In a **third terminal**, trigger a test event:

```bash
stripe trigger payment_intent.succeeded
```

Watch the terminal running `stripe listen` - you should see the event being forwarded to your app!

### Test 2: Create a Real Test Subscription

1. Start your app: `composer dev`
2. Visit: http://192.168.1.51:8080/billing
3. Add a payment method (use test card: `4242 4242 4242 4242`)
4. Subscribe to a plan
5. Check your `stripe listen` terminal for webhook events:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `invoice.payment_succeeded`

### Test 3: Verify Events in Database

```bash
php artisan tinker
```

```php
// Check billing events
\App\Models\BillingEvent::latest()->take(5)->get();

// Should show recent webhook events logged
```

---

## 🔍 Common Test Scenarios

### Test Failed Payment

```bash
stripe trigger invoice.payment_failed
```

Check:
- BillingEvent created in database
- Webhook handled correctly

### Test Subscription Update

```bash
stripe trigger customer.subscription.updated
```

### Test Subscription Cancellation

```bash
stripe trigger customer.subscription.deleted
```

---

## ✅ Verifying Everything Works

### Checklist:

- [ ] Stripe CLI installed and logged in
- [ ] `stripe listen` running and forwarding to local server
- [ ] Webhook secret updated in `.env`
- [ ] Dev server restarted
- [ ] Can add payment method on `/billing` page
- [ ] Can subscribe to a plan
- [ ] Webhook events appear in `stripe listen` output
- [ ] BillingEvent records created in database
- [ ] No errors in Laravel logs (`php artisan pail`)

---

## 🚀 Production Webhook Setup

### Step 1: Register Webhook Endpoint in Stripe

1. Go to: https://dashboard.stripe.com/test/webhooks
2. Click **"Add endpoint"**
3. **Endpoint URL**: `https://yourdomain.com/stripe/webhook`
4. **Events to send**: Select these events:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`

   Or select **"Select all events"** for comprehensive coverage

5. Click **"Add endpoint"**

### Step 2: Get Production Webhook Secret

1. Click on the webhook endpoint you just created
2. Click **"Reveal"** under **Signing secret**
3. Copy the secret (starts with `whsec_`)

### Step 3: Update Production .env

In your production `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_production_secret_here
```

### Step 4: Test Production Webhook

Stripe Dashboard → Webhooks → Your endpoint → **"Send test webhook"**

Select an event and send it to verify your production endpoint is working.

---

## 🔒 Security Notes

- ✅ Webhook signature verification is automatic (handled by Laravel Cashier)
- ✅ Endpoint excluded from CSRF protection
- ✅ Only processes events from Stripe (verified signatures)
- ⚠️ Never disable signature verification
- ⚠️ Keep `STRIPE_WEBHOOK_SECRET` secret and secure

---

## 🐛 Troubleshooting

### "No signatures found" error

**Problem**: Webhook secret not set or incorrect

**Solution**:
1. Check `.env` has `STRIPE_WEBHOOK_SECRET` set
2. Restart dev server: `composer dev`
3. Verify secret matches what `stripe listen` shows

### Webhooks not received

**Problem**: `stripe listen` not forwarding or endpoint unreachable

**Solution**:
1. Ensure `stripe listen` is running
2. Check URL matches your local server
3. Verify Laravel app is running on that URL

### Events not logged in database

**Problem**: Webhook handler not working

**Solution**:
1. Check Laravel logs: `php artisan pail`
2. Verify `StripeWebhookController` methods are being called
3. Check `BillingEvent` model can create records

### Database connection errors

**Problem**: Fresh migration cleared all data

**Solution**:
1. Re-run seeder: `php artisan db:seed --class=SubscriptionPlanSeeder`
2. Create a test user if needed
3. Test again

---

## 📊 Monitoring Webhooks

### View Recent Webhook Deliveries

Stripe Dashboard → Developers → Webhooks → Click on your endpoint

You can see:
- All webhook deliveries
- Response codes
- Retry attempts
- Event details

### Manually Retry Failed Webhooks

If a webhook fails, you can manually retry it from the Stripe Dashboard.

---

## ✅ Phase 6 Complete Checklist

- [ ] Stripe CLI installed
- [ ] Successfully logged in to Stripe CLI
- [ ] Webhook forwarding working locally
- [ ] Webhook secret updated in `.env`
- [ ] Test subscription created successfully
- [ ] Webhook events appearing in logs
- [ ] BillingEvents created in database
- [ ] Production webhook endpoint documented
- [ ] Ready to deploy to production

---

## 🎉 Next Steps

After Phase 6, you're ready for:
- **Phase 7-8**: Testing & Polish
- **Phase 9**: Production Setup
- **Phase 10**: Deployment

---

## 📚 Resources

- Stripe CLI Docs: https://stripe.com/docs/stripe-cli
- Stripe Webhooks Guide: https://stripe.com/docs/webhooks
- Laravel Cashier Webhooks: https://laravel.com/docs/11.x/billing#handling-stripe-webhooks
- Test Cards: https://stripe.com/docs/testing#cards
