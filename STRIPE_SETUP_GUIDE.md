# Stripe Setup Guide

Follow these steps to configure Stripe products, prices, and seed your database.

---

## Step 1: Create Products in Stripe Dashboard

1. **Go to Stripe Dashboard**
   - Visit: https://dashboard.stripe.com/test/products
   - Make sure you're in **Test mode** (toggle in top right)

2. **Create Free Plan Product** (for reference only, won't create subscriptions)
   - Click **"+ Add product"**
   - **Name**: `Free Plan`
   - **Description**: `Limited to 1 server with basic features`
   - **Pricing model**: One time (we won't use this, just for reference)
   - Click **"Save product"**
   - **Note the Product ID** (starts with `prod_`)

3. **Create Pro Plan Product**
   - Click **"+ Add product"**
   - **Name**: `Pro Plan`
   - **Description**: `Up to 10 servers with advanced monitoring and priority support`
   - **Pricing model**: Recurring
   - Click **"Save product"**
   - **Note the Product ID** (starts with `prod_`)

4. **Create Enterprise Plan Product**
   - Click **"+ Add product"**
   - **Name**: `Enterprise Plan`
   - **Description**: `Up to 100 servers with enterprise features and dedicated support`
   - **Pricing model**: Recurring
   - Click **"Save product"**
   - **Note the Product ID** (starts with `prod_`)

---

## Step 2: Create Prices for Each Product

### Pro Plan Prices

1. **Navigate to Pro Plan product page**
   - Click on "Pro Plan" from products list

2. **Create Monthly Price**
   - Click **"Add another price"**
   - **Price**: `€19.00` (or your preferred amount)
   - **Billing period**: Monthly
   - **Price description**: `Pro Monthly`
   - Click **"Save"**
   - **Copy the Price ID** (starts with `price_`) - this is `STRIPE_PRICE_PRO_MONTHLY`

3. **Create Yearly Price**
   - Click **"Add another price"**
   - **Price**: `€190.00` (typically 20% discount from monthly × 12)
   - **Billing period**: Yearly
   - **Price description**: `Pro Yearly`
   - Click **"Save"**
   - **Copy the Price ID** (starts with `price_`) - this is `STRIPE_PRICE_PRO_YEARLY`

### Enterprise Plan Prices

1. **Navigate to Enterprise Plan product page**
   - Click on "Enterprise Plan" from products list

2. **Create Monthly Price**
   - Click **"Add another price"**
   - **Price**: `€99.00` (or your preferred amount)
   - **Billing period**: Monthly
   - **Price description**: `Enterprise Monthly`
   - Click **"Save"**
   - **Copy the Price ID** (starts with `price_`) - this is `STRIPE_PRICE_ENTERPRISE_MONTHLY`

3. **Create Yearly Price**
   - Click **"Add another price"**
   - **Price**: `€990.00` (typically 20% discount from monthly × 12)
   - **Billing period**: Yearly
   - **Price description**: `Enterprise Yearly`
   - Click **"Save"**
   - **Copy the Price ID** (starts with `price_`) - this is `STRIPE_PRICE_ENTERPRISE_YEARLY`

---

## Step 3: Update .env File

Open your `.env` file and update these values with the Price IDs you copied:

```env
# Subscription Plans (Price IDs from Stripe Dashboard)
STRIPE_PRICE_FREE=
STRIPE_PRICE_PRO_MONTHLY=price_xxxxxxxxxxxxx
STRIPE_PRICE_PRO_YEARLY=price_xxxxxxxxxxxxx
STRIPE_PRICE_ENTERPRISE_MONTHLY=price_xxxxxxxxxxxxx
STRIPE_PRICE_ENTERPRISE_YEARLY=price_xxxxxxxxxxxxx
```

**Note**: Leave `STRIPE_PRICE_FREE` empty - free plan doesn't need a Stripe price.

---

## Step 4: Run Database Seeder

Once you've updated the `.env` file, run:

```bash
php artisan db:seed --class=SubscriptionPlanSeeder
```

This will populate the `subscription_plans` table with all your plan data from Stripe.

---

## Step 5: Verify Setup

1. **Check Database**
   ```bash
   php artisan tinker
   ```

   Then run:
   ```php
   \App\Models\SubscriptionPlan::all();
   ```

   You should see all your plans listed.

2. **Visit Billing Page**
   - Start your dev server: `composer dev`
   - Navigate to `/billing`
   - You should see all your plans displayed correctly

---

## Troubleshooting

### "Price not found" errors
- Double-check the Price IDs in your `.env` file
- Make sure you're using Test mode price IDs (start with `price_test_`)
- Restart your dev server after updating `.env`

### Prices showing as $0.00
- Verify currency is set correctly: `CASHIER_CURRENCY=eur`
- Check that you created prices in EUR, not USD
- Clear Laravel cache: `php artisan config:clear`

### Seeder fails
- Run migrations first: `php artisan migrate`
- Make sure `.env` has valid Stripe keys
- Check database connection is working

---

## Summary of What You'll Have

After completing these steps:

- ✅ 3 Products in Stripe (Free, Pro, Enterprise)
- ✅ 4 Prices in Stripe (Pro Monthly/Yearly, Enterprise Monthly/Yearly)
- ✅ All Price IDs configured in `.env`
- ✅ `subscription_plans` table populated with plan data
- ✅ Billing page showing all available plans

---

## Next Steps

Once setup is complete, you can:
1. Test subscribing to a plan using Stripe test cards
2. Configure webhooks for production
3. Test the complete subscription flow

**Stripe Test Cards:**
- Success: `4242 4242 4242 4242`
- Decline: `4000 0000 0000 0002`
- Requires authentication: `4000 0025 0000 3155`

Use any future expiry date, any 3-digit CVC, and any postal code.
