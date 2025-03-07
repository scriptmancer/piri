#!/bin/bash

# Cleanup script for Piri Router
# This script removes temporary files before pushing to GitHub

echo "Cleaning up temporary files..."

# Remove test files in example/public
echo "Removing test files in example/public..."
find ./example/public -type f -name "test_*.php" -delete

# Remove OS-specific files
echo "Removing OS-specific files..."
find . -name ".DS_Store" -delete
find . -name "._*" -delete

# Remove other temporary files
echo "Removing other temporary files..."
rm -f composer.lock
rm -f phpunit.xml

echo "Cleanup complete!" 