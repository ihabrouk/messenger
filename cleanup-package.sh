#!/bin/bash

# Final package cleanup script
# This script fixes any remaining namespace and path issues

echo "🔧 Running final package cleanup..."

cd "$(dirname "$0")"

# Fix any remaining App\Messenger references that might have been missed
echo "🔄 Fixing remaining namespace references..."
find src -name "*.php" -type f -exec grep -l "App\\\\Messenger" {} \; | while read file; do
    echo "Fixing $file"
    sed -i '' 's/App\\Messenger/Ihabrouk\\Messenger/g' "$file"
done

# Fix config path references in the main service provider
echo "🔧 Fixing config path references..."
if [ -f "src/MessengerServiceProvider.php" ]; then
    sed -i '' 's/__DIR__ \. "\/\.\.\/\.\.\/\.\.\/config\/messenger\.php"/__DIR__ \. "\/\.\.\/config\/messenger\.php"/g' src/MessengerServiceProvider.php
fi

# Fix migration path references
echo "🔧 Fixing migration path references..."
find src -name "*.php" -type f -exec sed -i '' 's/__DIR__ \. "\/\.\.\/database\/migrations\/"/__DIR__ \. "\/Database\/migrations\/"/g' {} \;

# Fix view path references
echo "🔧 Fixing view path references..."
find src -name "*.php" -type f -exec sed -i '' 's/__DIR__ \. "\/\.\.\/\.\.\/\.\.\/resources\/views"/__DIR__ \. "\/\.\.\/resources\/views"/g' {} \;

# Fix language path references
echo "🔧 Fixing language path references..."
find src -name "*.php" -type f -exec sed -i '' 's/__DIR__ \. "\/\.\.\/\.\.\/\.\.\/lang"/__DIR__ \. "\/\.\.\/resources\/lang"/g' {} \;

echo "✅ Final cleanup completed!"
echo ""
echo "📦 Package structure:"
find . -type f -name "*.php" | head -20
echo "... and more files"
