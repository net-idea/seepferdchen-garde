#!/bin/bash

# Start the server and monitor logs
echo "Starting Symfony Development Server..."
php -S localhost:8000 -t public &
SERVER_PID=$!

echo "Server started with PID: $SERVER_PID"
echo ""
echo "✅ Server is running at: http://localhost:8000"
echo ""
echo "To test the booking form:"
echo "1. Open: http://localhost:8000/anmeldung"
echo "2. Fill out and submit the form"
echo "3. Check logs in another terminal: tail -f var/log/prod.log"
echo ""
echo "To stop the server later:"
echo "  kill $SERVER_PID"
echo ""
echo "Press Ctrl+C to stop monitoring (server will continue running)"
echo "---"
echo ""

# Monitor logs
tail -f var/log/prod.log 2>/dev/null &
tail -f var/log/dev-php-server.log 2>/dev/null

# Cleanup on exit
trap "kill $SERVER_PID 2>/dev/null" EXIT
