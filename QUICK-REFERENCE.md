# Quick Reference: Booking Form Fix

## What Was Fixed

✅ **Database-First Approach**: Bookings are now saved to the database BEFORE attempting to send email
✅ **Better Error Handling**: All exceptions are caught and logged with context
✅ **Clear User Feedback**: Different error messages for database vs email failures
✅ **Production Logging**: Logs now go to `var/log/prod.log` for debugging

## Commands for Production

### 1. Deploy Changes
```bash
# On production server
cd /path/to/seepferdchen-garde
git pull origin main
php bin/console cache:clear --env=prod
chmod -R 777 var/log var/cache
```

### 2. Check Configuration
```bash
php test-mail-config.php
```

### 3. Monitor Logs
```bash
tail -f var/log/prod.log
```

### 4. Check for Unconfirmed Bookings
```bash
bash check-unconfirmed-bookings.sh
```

### 5. View Recent Bookings
```bash
sqlite3 var/data_prod.db "SELECT id, created_at, parent_email, child_name, confirmed_at FROM form_booking ORDER BY id DESC LIMIT 10;"
```

## If Email Sending Still Fails

1. **Check MAILER_DSN is set correctly** in `.env.local`:
   ```bash
   grep MAILER_DSN .env.local
   ```

2. **Test mail server connection**:
   - Host: mail.seepferdchen-garde.de
   - Port: 587
   - Encryption: TLS
   - Username: mail@seepferdchen-garde.de

3. **Check logs for specific error**:
   ```bash
   grep -i "ERROR" var/log/prod.log | tail -20
   ```

4. **Manually resend confirmation** for unconfirmed bookings:
   ```bash
   # Get token
   sqlite3 var/data_prod.db "SELECT id, confirmation_token FROM form_booking WHERE confirmed_at IS NULL;"
   
   # Send link: https://seepferdchen-garde.de/anmeldung/bestaetigen/{token}
   ```

## Error Messages Explained

- **error=db**: Database failure - booking NOT saved. User should try again.
- **error=mail**: Booking saved successfully, but email failed. User is informed that you will contact them.
- **error=rate**: Rate limit hit. User is submitting too fast.

## The Fix Guarantees

1. **No Data Loss**: If the form is valid, the booking is ALWAYS saved to database
2. **Transparency**: Users are clearly told if email failed (but data was saved)
3. **Debuggability**: All operations are logged with booking IDs and email addresses
4. **Recovery**: Unconfirmed bookings can be manually followed up using provided scripts
