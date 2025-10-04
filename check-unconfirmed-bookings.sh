#!/bin/bash

# Script to check for unconfirmed bookings that need manual follow-up
# Usage: bash check-unconfirmed-bookings.sh

DB="var/data_prod.db"

if [ ! -f "$DB" ]; then
    echo "❌ Database not found: $DB"
    exit 1
fi

echo "=== Checking Unconfirmed Bookings ==="
echo ""

UNCONFIRMED=$(sqlite3 "$DB" "SELECT COUNT(*) FROM form_booking WHERE confirmed_at IS NULL;")

if [ "$UNCONFIRMED" -eq 0 ]; then
    echo "✅ No unconfirmed bookings found."
    exit 0
fi

echo "⚠️  Found $UNCONFIRMED unconfirmed booking(s):"
echo ""

sqlite3 -header -column "$DB" << 'EOF'
SELECT
    id,
    datetime(created_at) as created,
    parent_name,
    parent_email,
    child_name
FROM form_booking
WHERE confirmed_at IS NULL
ORDER BY created_at DESC;
EOF

echo ""
echo "=== Action Required ==="
echo "These bookings were saved but not confirmed (email may have failed)."
echo "Please contact these parents manually to confirm their bookings."
echo ""
echo "To manually confirm a booking, run:"
echo "  sqlite3 $DB \"UPDATE form_booking SET confirmed_at = datetime('now') WHERE id = X;\""
echo ""
echo "Or send them the confirmation link:"
echo "  https://seepferdchen-garde.de/anmeldung/bestaetigen/{token}"
echo ""
echo "To get the token for booking ID X:"
echo "  sqlite3 $DB \"SELECT confirmation_token FROM form_booking WHERE id = X;\""
