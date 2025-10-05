# Stripe Quick Start - Step by Step

Follow these exact steps to get your Stripe billing up and running.

---

## ✅ Step 1: Create Products in Stripe Dashboard (5 minutes)

### Go to Stripe Products
🔗 **Link**: https://dashboard.stripe.com/test/products

Make sure **Test mode** toggle is ON (top right corner).

### Create 2 Products:

#### 1. Pro Plan
- Click **"+ Add product"**
- Name: `Pro Plan`
- Description: `Up to 10 servers with advanced monitoring`
- Save product
- **📝 Save the Product ID** (starts with `prod_`)

#### 2. Enterprise Plan
- Click **"+ Add product"**
- Name: `Enterprise Plan`
- Description: `Up to 100 servers with enterprise features`
- Save product
- **📝 Save the Product ID** (starts with `prod_`)

---

## ✅ Step 2: Create Prices for Each Product (5 minutes)

### For Pro Plan:

1. **Click on Pro Plan** from products list
2. **Add Monthly Price**:
   - Price: `€19.00` (suggested)
   - Billing period: **Monthly**
   - Description: `Pro Monthly`
   - Save
   - **📝 Copy Price ID** → This is your `STRIPE_PRICE_PRO_MONTHLY`

3. **Add Yearly Price**:
   - Click "Add another price"
   - Price: `€190.00` (suggested - 20% discount)
   - Billing period: **Yearly**
   - Description: `Pro Yearly`
   - Save
   - **📝 Copy Price ID** → This is your `STRIPE_PRICE_PRO_YEARLY`

### For Enterprise Plan:

1. **Click on Enterprise Plan** from products list
2. **Add Monthly Price**:
   - Price: `€99.00` (suggested)
   - Billing period: **Monthly**
   - Description: `Enterprise Monthly`
   - Save
   - **📝 Copy Price ID** → This is your `STRIPE_PRICE_ENTERPRISE_MONTHLY`

3. **Add Yearly Price**:
   - Click "Add another price"
   - Price: `€990.00` (suggested - 20% discount)
   - Billing period: **Yearly**
   - Description: `Enterprise Yearly`
   - Save
   - **📝 Copy Price ID** → This is your `STRIPE_PRICE_ENTERPRISE_YEARLY`

---

## ✅ Step 3: Update .env File (1 minute)

Open `D:\Project\Php\BrokeForge\.env` and update these lines with your Price IDs:

```env
# Subscription Plans (Price IDs from Stripe Dashboard)
STRIPE_PRICE_FREE=
STRIPE_PRICE_PRO_MONTHLY=price_xxxxxxxxxxxxx        ← Replace with your Pro Monthly Price ID
STRIPE_PRICE_PRO_YEARLY=price_xxxxxxxxxxxxx         ← Replace with your Pro Yearly Price ID
STRIPE_PRICE_ENTERPRISE_MONTHLY=price_xxxxxxxxxxxxx ← Replace with your Enterprise Monthly Price ID
STRIPE_PRICE_ENTERPRISE_YEARLY=price_xxxxxxxxxxxxx  ← Replace with your Enterprise Yearly Price ID
```

**Save the file!**

---

## ✅ Step 4: Run Database Seeder (30 seconds)

Open your terminal and run:

```bash
php artisan db:seed --class=SubscriptionPlanSeeder
```

You should see:
```
✓ Created Pro Monthly plan
✓ Created Pro Yearly plan
✓ Created Enterprise Monthly plan
✓ Created Enterprise Yearly plan

✓ Seeded 4 subscription plans successfully!
```

---

## ✅ Step 5: Verify Everything Works (1 minute)

### Check Database:
```bash
php artisan tinker
```

Then run:
```php
\App\Models\SubscriptionPlan::all();
exit
```

You should see 4 plans with correct prices.

### Check Billing Page:
1. Start dev server: `composer dev`
2. Visit: http://192.168.1.51:8080/billing
3. You should see:
   - Current Plan (Free)
   - Server Usage
   - Available Plans (Pro & Enterprise with monthly/yearly toggle)
   - Payment Methods section
   - Invoices section

---

## 🎉 Done!

Your Stripe integration is now set up and ready to test!

---

## 🧪 Test Your Setup

### Test Cards (Use any future expiry, any CVC, any postal code):

- ✅ **Success**: `4242 4242 4242 4242`
- ❌ **Decline**: `4000 0000 0000 0002`
- 🔐 **Requires Auth**: `4000 0025 0000 3155`

### Test Flow:

1. Go to `/billing`
2. Click "Add Payment Method"
3. Enter test card: `4242 4242 4242 4242`
4. Click "Add Payment Method"
5. Select a plan and click "Start Trial" or "Upgrade"
6. Subscription should be created!

---

## 📋 Checklist

- [ ] Created Pro Plan product in Stripe
- [ ] Created Enterprise Plan product in Stripe
- [ ] Created 2 prices for Pro Plan (monthly + yearly)
- [ ] Created 2 prices for Enterprise Plan (monthly + yearly)
- [ ] Updated `.env` with all 4 Price IDs
- [ ] Ran `php artisan db:seed --class=SubscriptionPlanSeeder`
- [ ] Verified plans in database with tinker
- [ ] Visited `/billing` page and saw plans displayed
- [ ] Tested adding a payment method with test card

---

## Need Help?

- **Prices showing $0.00?**
  - Run: `php artisan config:clear`
  - Restart dev server: `composer dev`

- **Seeder failed?**
  - Check Price IDs are correct in `.env`
  - Make sure Stripe keys are set correctly
  - Verify you're in Test mode in Stripe Dashboard

- **Can't see plans on billing page?**
  - Check browser console for errors
  - Make sure `VITE_STRIPE_KEY` is set in `.env`
  - Run: `npm run build` and restart server

---

See `STRIPE_SETUP_GUIDE.md` for more detailed information.
