#!/bin/bash

# Equipment Borrowing System - Development Server Starter

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo "🚀 Starting Equipment Borrowing System..."
echo ""

# Start PHP backend server
echo "📦 Starting Laravel Backend (Port 8000)..."
cd "$SCRIPT_DIR/backend"

# The storage symlink (serves uploaded equipment photos) doesn't survive a
# zip/extract or a copy to another computer — recreate it if missing so
# photos don't silently 404 on a fresh machine.
if [ ! -e "public/storage" ]; then
    echo "🔗 Linking storage folder (equipment photos)..."
    php artisan storage:link
fi

php artisan serve &
BACKEND_PID=$!
echo "   Backend PID: $BACKEND_PID"
sleep 2

# Start Vite frontend dev server
echo "⚛️  Starting Vite Frontend (Port 5174)..."
cd "$SCRIPT_DIR/backend"
npm run dev &
FRONTEND_PID=$!
echo "   Frontend PID: $FRONTEND_PID"
sleep 2

echo ""
echo "✅ Both servers are running!"
echo ""
echo "📍 Access your application:"
echo "   Backend:  http://127.0.0.1:8000"
echo "   Frontend: http://localhost:5174"
echo ""
echo "Press Ctrl+C to stop both servers"
echo ""

# Wait for both processes
wait
