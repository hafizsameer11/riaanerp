# Installing phpseclib for Firewall Monitor

The Firewall Monitor System uses **phpseclib** instead of the PHP SSH2 extension for SSH connections. phpseclib is a pure PHP implementation that doesn't require any PHP extensions.

## Installation Methods

### Method 1: Via Composer (Recommended)

If your project uses Composer:

```bash
cd /path/to/your/project
composer require phpseclib/phpseclib
```

This will install phpseclib in the `vendor/` directory and the autoloader will automatically load it.

### Method 2: Manual Installation

If you don't use Composer:

1. **Download phpseclib:**
   ```bash
   cd /path/to/your/project
   git clone https://github.com/phpseclib/phpseclib.git
   ```

   OR download from: https://github.com/phpseclib/phpseclib/releases

2. **The library should be in:**
   ```
   /path/to/your/project/phpseclib/phpseclib/
   ```

3. **Update the autoloader** or ensure the Composer autoloader can find it.

### Method 3: Using Existing Composer Setup

If you already have a `composer.json` file:

1. **Add phpseclib to composer.json:**
   ```json
   {
       "require": {
           "phpseclib/phpseclib": "^3.0"
       }
   }
   ```

2. **Install:**
   ```bash
   composer install
   ```

## Verify Installation

After installation, verify it works:

```bash
php -r "require 'vendor/autoload.php'; echo class_exists('phpseclib3\Net\SSH2') ? 'phpseclib installed!' : 'Not found';"
```

## Testing SSH Connection

Use the test script:

```bash
cd modules/firewall
php tests/test_ssh.php
```

## Troubleshooting

### Error: "phpseclib not found"

**Solution:**
1. Make sure Composer autoloader is loaded
2. Check that `vendor/autoload.php` exists
3. Verify phpseclib is in `vendor/phpseclib/phpseclib/`

### Error: "Class not found"

**Solution:**
- Run `composer dump-autoload` to regenerate autoloader
- Check that `vendor/autoload.php` is being loaded in `functions.php`

### Error: "SSH connection failed"

**Solution:**
- Verify SSH credentials are correct
- Check firewall allows port 22
- Test SSH manually: `ssh user@host`
- Verify network connectivity

## Benefits of phpseclib

✅ **No PHP Extensions Required** - Pure PHP implementation  
✅ **Works on Any Server** - No need to install SSH2 extension  
✅ **Better Compatibility** - Works on shared hosting  
✅ **Active Maintenance** - Regularly updated  
✅ **More Features** - Supports SSH keys, SFTP, etc.

## Version Compatibility

The code supports:
- **phpseclib 3.x** (recommended)
- **phpseclib 2.x** (legacy)
- **phpseclib 1.x** (very old)

---

**Last Updated:** 2024-01-15

