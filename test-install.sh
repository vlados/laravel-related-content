#!/bin/bash

# Test package install/uninstall script
# Usage: ./test-install.sh [iterations]

set -e

ITERATIONS=${1:-3}
PACKAGE_NAME="vlados/laravel-related-content"
TEST_DIR="/tmp/laravel-test-install"

echo "Testing $PACKAGE_NAME installation $ITERATIONS times"
echo "Source: https://packagist.org/packages/$PACKAGE_NAME"
echo ""

# Clean up any existing test directory
rm -rf "$TEST_DIR"

# Create new Laravel project once
echo "Creating Laravel project..."
composer create-project laravel/laravel "$TEST_DIR" --quiet --no-interaction

cd "$TEST_DIR"

for i in $(seq 1 $ITERATIONS); do
    echo "[$i/$ITERATIONS] Installing..."
    composer require "$PACKAGE_NAME:@dev" --quiet --no-interaction

    if php artisan related-content:rebuild --help > /dev/null 2>&1; then
        echo "[$i/$ITERATIONS] ✓ Installed"
    else
        echo "[$i/$ITERATIONS] ✗ Install failed"
        exit 1
    fi

    echo "[$i/$ITERATIONS] Removing..."
    composer remove "$PACKAGE_NAME" --quiet --no-interaction

    if ! composer show "$PACKAGE_NAME" > /dev/null 2>&1; then
        echo "[$i/$ITERATIONS] ✓ Removed"
    else
        echo "[$i/$ITERATIONS] ✗ Remove failed"
        exit 1
    fi

    echo ""
done

# Cleanup
rm -rf "$TEST_DIR"

echo "All $ITERATIONS iterations completed successfully!"
