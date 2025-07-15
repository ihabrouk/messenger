#!/bin/bash

# Migration Cleanup Script
# This script helps clean up existing messenger tables before installing the package

echo "🗃️  Messenger Migration Cleanup"
echo "This script will help you clean up existing messenger tables"
echo "before installing the messenger package."
echo ""

read -p "⚠️  This will DROP all messenger tables. Are you sure? (y/N): " -n 1 -r
echo ""

if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🗑️  Dropping messenger tables..."
    
    php artisan tinker --execute="
    try {
        Schema::dropIfExists('messenger_consents');
        Schema::dropIfExists('messenger_contacts');
        Schema::dropIfExists('messenger_webhooks');
        Schema::dropIfExists('messenger_logs');
        Schema::dropIfExists('messenger_batches');
        Schema::dropIfExists('messenger_templates');
        Schema::dropIfExists('messenger_messages');
        echo 'Tables dropped successfully\n';
    } catch (Exception \$e) {
        echo 'Error: ' . \$e->getMessage() . '\n';
    }
    "
    
    echo "🗑️  Removing migration files..."
    rm -f database/migrations/*messenger*.php
    rm -f database/migrations/*create_messenger*.php
    
    echo "✅ Cleanup completed!"
    echo ""
    echo "📦 Now you can install the package:"
    echo "composer require ihabrouk/messenger"
    echo "php artisan vendor:publish --tag=\"messenger-config\""
    echo "php artisan vendor:publish --tag=\"messenger-migrations\""
    echo "php artisan migrate"
else
    echo "❌ Cleanup cancelled."
fi
