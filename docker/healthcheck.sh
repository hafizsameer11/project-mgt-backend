#!/bin/sh
# Health check script to verify PHP-FPM is ready

for i in 1 2 3 4 5; do
    if nc -z 127.0.0.1 9000 2>/dev/null; then
        echo "PHP-FPM is ready on port 9000"
        exit 0
    fi
    echo "Waiting for PHP-FPM... ($i/5)"
    sleep 1
done

echo "PHP-FPM health check failed"
exit 1

