# OTP Tool (PHP + Encrypted JSON Store)

A simple one-time-password tool using:
- PHP API for consume/count
- AES-256-CBC encryption
- key derived from SHA-256(secret)
- encrypted store format: base64(iv + cipher)

## Files
- `index.php` - main operator UI
- `otp_api.php` - consume/count API
- `pool_generator.php` - standalone encrypted pool generator
- `password_store.json` - encrypted store file (you generate this)
- `.htaccess` - blocks direct web access to the store on Apache

## Folder layout

```text
/your-folder/
├── index.php
├── otp_api.php
├── pool_generator.php
├── password_store.json
└── .htaccess
```

## How it works
1. Create a pool using `pool_generator.php` or the generator on `index.php`.
2. Upload the generated `password_store.json` beside the PHP files.
3. Open `index.php`.
4. Enter the same secret used when the pool was created.
5. Click **Generate OTP**.
6. The server decrypts the store, removes the first OTP, re-encrypts the store, saves it, and returns the OTP.

## Requirements
- PHP with OpenSSL enabled
- Web server with write access to `password_store.json`

## Permissions
The PHP process must be able to read and write `password_store.json`.

Typical Linux example:

```bash
chmod 664 password_store.json
```

If writes still fail, the folder permissions may also need adjustment.

## Security notes
- The store should not be publicly downloadable.
- `.htaccess` helps on Apache, but if you use Nginx, block direct access in your server config.
- The secret is not stored by the app. The operator enters it each session.
- Generate already consumes the OTP. Copy only copies it to clipboard.

## Troubleshooting
If you see errors like:
- `Store file not found` -> upload `password_store.json` to the same folder as `otp_api.php`
- `Decryption failed` -> wrong secret or invalid file content
- `Failed to write updated store file` -> file permissions issue

## Recommended next upgrades
- operator login session
- audit log of consumed OTPs
- rate limiting
- admin page to upload/replace the pool
