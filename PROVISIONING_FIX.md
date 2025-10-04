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

## Date Fixed
2025-10-04
