# 🧪 Messenger Demo Components

⚠️ **IMPORTANT: These components are for demonstration and testing purposes ONLY**

## 📋 What's in this directory?

This directory contains demo/example components that show how the Messenger package can work, but **should NOT be used in production applications**.

### 📁 Directory Structure

```
Demo/
├── Models/
│   └── Contact.php          # Demo contact model
├── Factories/
│   └── ContactFactory.php   # Factory for creating test contacts
├── Seeders/
│   └── ContactSeeder.php    # Seeder for demo data
└── README.md               # This file
```

## 🎯 Purpose

### ✅ Good for:
- **Testing the package** during development
- **Learning how messaging works** with a complete example
- **Prototyping** new messaging features
- **CI/CD testing** with sample data
- **Package development** and validation

### ❌ NOT for:
- **Production applications** with existing user systems
- **Real customer data** management
- **Actual messaging** to real users

## 🚀 Usage

### For Testing/Development

```php
use App\Messenger\Demo\Models\Contact;

// Create test contacts
$contact = Contact::factory()->active()->create();

// Test messaging
$messenger->send([
    'recipient_phone' => $contact->phone_number,
    'content' => 'Test message',
]);
```

### For Seeding Test Data

```php
// In your test database seeder
use App\Messenger\Demo\Seeders\ContactSeeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        if (app()->environment(['local', 'testing'])) {
            $this->call(ContactSeeder::class);
        }
    }
}
```

## 🏗️ Production Integration

Instead of using these demo components, integrate with your existing models:

### Option 1: Use Your Existing User Model

```php
// Your existing User model - no changes needed!
class User extends Model
{
    protected $fillable = ['name', 'email', 'phone_number'];
    
    // Optional: Add relationship to messages
    public function messages()
    {
        return $this->hasMany(Message::class, 'recipient_phone', 'phone_number');
    }
}

// Send messages to your users
$messenger->send([
    'recipient_phone' => $user->phone_number,
    'content' => 'Hello ' . $user->name,
    'messageable_type' => User::class,
    'messageable_id' => $user->id,
]);
```

### Option 2: Add Messaging Preferences to Existing Model

```php
// Add migration to your users table
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_opted_in_messaging')->default(true);
    $table->json('messaging_preferences')->nullable();
});

// Use your enhanced User model
class User extends Model
{
    protected $casts = [
        'messaging_preferences' => 'array',
        'is_opted_in_messaging' => 'boolean',
    ];
    
    public function canReceiveMessages(): bool
    {
        return $this->is_opted_in_messaging;
    }
}
```

## 📚 Documentation

For complete integration instructions, see:
- [Integration Guide](../docs/INTEGRATION.md)
- [Main Package Documentation](../../README.md)

## 🔧 Migration Path

If you're currently using the demo Contact model and want to migrate to your existing User model:

1. **Export your data** from messenger_contacts
2. **Map the data** to your existing user table
3. **Update your code** to use User model instead of Contact
4. **Remove** the messenger_contacts table and Contact model
5. **Update relationships** in Message model if needed

## ⚡ Quick Start (Testing Only)

```bash
# Run migrations (includes messenger_contacts for demo)
php artisan migrate

# Seed demo data
php artisan db:seed --class=\\App\\Messenger\\Demo\\Seeders\\ContactSeeder

# Test messaging
php artisan tinker
>>> $contact = App\Messenger\Demo\Models\Contact::first()
>>> // Send test message using your preferred method
```

---

**Remember**: These demo components help you understand and test the package, but production applications should integrate with existing user models for the best experience! 🎯
