# Provisioning Password Fix

## Problem

When retrying server provisioning, the error occurred:
```
App\Packages\ProvisionAccess::makeScriptFor(): Argument #2 ($rootPassword) must be of type string, null given
```

## Root Cause

The `retry()` method in `ServerProvisioningController` was setting `ssh_root_password = null`, but the password generation only happens during the `creating` event (when a new server is first created), NOT during `save()` on existing records.

## Solution Applied

### 1. Made `generatePassword()` public in Server model

**File:** `app/Models/Server.php:310`

Changed:
```php
protected static function generatePassword(int $length = 24): string
```

To:
```php
public static function generatePassword(int $length = 24): string
```

### 2. Fixed retry() method to regenerate password

**File:** `app/Http/Controllers/ServerProvisioningController.php:132`

Changed:
```php
// Reset root password so a new secret is generated for the next attempt
$server->ssh_root_password = null;
```

To:
```php
// Generate new root password for the next attempt
$server->ssh_root_password = \App\Models\Server::generatePassword();
```

## IP Address Issue Fix

The Multipass VM had IP `192.168.1.57` but the database had `192.168.56.1` (wrong) and `10.0.2.15` (NAT IP). Updated to correct host-accessible IP:

```php
$server = App\Models\Server::find(14);
$server->update(['public_ip' => '192.168.1.57']);
```

Use `multipass list` to find the correct IP address.

## How to Test

1. Get Multipass VM IP: `multipass list`
2. Update server IP in database if needed
3. Run provision script on VM:
   ```bash
   wget -O laravel.sh "http://192.168.1.51:8080/servers/14/provision"; bash laravel.sh
   ```

## Windows Multipass Bridge Setup

When creating a new Multipass instance on Windows, a network bridge must be configured to use the WiFi adapter for proper network connectivity.

**Why this is needed:**
- Without bridging, the VM only gets NAT IP (10.x.x.x) which is not accessible from the host
- Bridging to WiFi adapter gives the VM an IP on the same network as the host (192.168.x.x)
- This allows BrokeForge to connect via SSH from the host machine

### Step-by-Step Setup

**1. Find Your WiFi Adapter Name**
```bash
multipass networks
```
Look for your WiFi adapter (usually named "WiFi" or "Wi-Fi")

**2. Launch Instance with Bridge**
```bash
multipass launch --name ubuntu-vm --network "WiFi"
```
Or if your adapter has a different name from step 1:
```bash
multipass launch --name ubuntu-vm --network "Your-Adapter-Name"
```

**3. Verify Instance is Running**
```bash
multipass list
```
You should see your instance with an IP like `192.168.x.x` (not just `10.x.x.x`)

**4. Get Shell Access**
```bash
multipass shell ubuntu-vm
```

**5. Inside VM - Verify Network**
```bash
ip addr show
hostname -I
```
You should see both:
- NAT IP (10.x.x.x) on one interface
- Bridged IP (192.168.x.x) on another interface

The bridged IP is what BrokeForge will use to connect.

**6. Note the Bridged IP**
From your host (not inside VM):
```bash
multipass list
```
Use the `192.168.x.x` IP when creating the server in BrokeForge.

## Date Fixed
2025-10-04
