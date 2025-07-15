# 📱 Messenger Package Integration Guide

## 🎯 Overview

The Messenger package is designed to work with **existing user models** in your Laravel application. You don't need to use our Contact model - it's just for demo/testing purposes.

## 🔌 Integration with Existing Models

### Option 1: Using Existing User/Member Model (Recommended)

The Messenger package works with phone numbers as identifiers, making it compatible with any existing model:

```php
// Your existing User model
class User extends Model
{
    protected $fillable = [
        'name',
        'email', 
        'phone_number', // This is all you need!
        // ... other fields
    ];
    
    // Optional: Add messaging preferences
    protected $casts = [
        'messaging_preferences' => 'array',
        'is_opted_in_messaging' => 'boolean',
    ];
    
    // Optional: Relationship to messages
    public function messages()
    {
        return $this->hasMany(\App\Messenger\Models\Message::class, 'recipient_phone', 'phone_number');
    }
}
```

### Option 2: Using Polymorphic Relationships (Advanced)

For more sophisticated integration, use the polymorphic `messageable` relationship:

```php
// Send message with model relationship
$message = Message::create([
    'recipient_phone' => $user->phone_number,
    'messageable_type' => User::class,
    'messageable_id' => $user->id,
    'content' => 'Hello!',
    // ... other fields
]);

// Access the related model
$user = $message->messageable; // Returns the User model
```

## 📧 Sending Messages

### Basic Usage (Phone Number Only)

```php
use App\Messenger\Services\MessengerService;

$messenger = app(MessengerService::class);

// Send to any phone number
$messenger->send([
    'recipient_phone' => '+1234567890',
    'content' => 'Your OTP is: 123456',
    'type' => 'otp',
    'provider' => 'sms_misr',
]);
```

### With Existing User Model

```php
// Get your existing user
$user = User::find(1);

// Send message using user's phone
$messenger->send([
    'recipient_phone' => $user->phone_number,
    'content' => "Hello {$user->name}! Welcome to our service.",
    'type' => 'welcome',
    'messageable_type' => User::class,
    'messageable_id' => $user->id,
]);
```

## 🎛️ FilamentPHP Integration

### Add Messaging to Your User Resource

```php
use App\Messenger\Actions\SendMessageAction;

class UserResource extends Resource
{
    // Add to your table actions
    public static function table(Table $table): Table
    {
        return $table
            ->actions([
                SendMessageAction::make()
                    ->phoneField('phone_number'), // Point to your phone field
                // ... other actions
            ]);
    }
}
```

### Bulk Messaging

```php
use App\Messenger\Actions\BulkMessageAction;

// Add to your resource
->bulkActions([
    BulkMessageAction::make()
        ->phoneField('phone_number')
        ->label('Send SMS to Selected'),
])
```

## 🏗️ Database Schema Integration

### Option 1: No Changes Required
If you just need basic messaging, no database changes are required. The package works with phone numbers.

### Option 2: Add Messaging Preferences (Optional)
Add these fields to your existing user table:

```php
// Migration
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_opted_in_messaging')->default(true);
    $table->timestamp('opted_in_at')->nullable();
    $table->timestamp('opted_out_at')->nullable();
    $table->json('messaging_preferences')->nullable();
});
```

### Option 3: Separate Messaging Preferences Table
Create a separate table for messaging preferences:

```php
// Migration
Schema::create('user_messaging_preferences', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->boolean('is_opted_in')->default(true);
    $table->timestamp('opted_in_at')->nullable();
    $table->timestamp('opted_out_at')->nullable();
    $table->json('preferences')->nullable();
    $table->timestamps();
});
```

## 📊 Analytics and Reporting

### Message History for Users

```php
// Get messages for a specific user
$userMessages = Message::where('recipient_phone', $user->phone_number)
    ->with(['template', 'logs'])
    ->orderBy('created_at', 'desc')
    ->get();

// Or use the relationship if you added it
$userMessages = $user->messages()->with(['template', 'logs'])->get();
```

### Delivery Analytics

```php
// Delivery rate for a user
$deliveryRate = Message::where('recipient_phone', $user->phone_number)
    ->where('status', 'delivered')
    ->count() / Message::where('recipient_phone', $user->phone_number)->count() * 100;
```

## 🧪 Testing with Demo Contact Model

For testing and development, you can use our demo Contact model:

```php
use App\Messenger\Demo\Models\Contact;

// Create test contacts
$contact = Contact::factory()->create([
    'phone_number' => '+1234567890',
    'first_name' => 'John',
    'last_name' => 'Doe',
]);

// Test messaging
$messenger->send([
    'recipient_phone' => $contact->phone_number,
    'content' => 'Test message',
]);
```

## 🚀 Best Practices

### 1. Phone Number Validation
Always validate and format phone numbers:

```php
// Add to your User model
public function setPhoneNumberAttribute($value)
{
    // Use a library like libphonenumber for proper formatting
    $this->attributes['phone_number'] = $this->formatPhoneNumber($value);
}
```

### 2. Opt-in/Opt-out Management
Respect user preferences:

```php
// Before sending messages
if (!$user->is_opted_in_messaging) {
    throw new Exception('User has opted out of messaging');
}
```

### 3. Error Handling
Handle messaging failures gracefully:

```php
try {
    $messenger->send($messageData);
} catch (MessengerException $e) {
    Log::error('Message failed: ' . $e->getMessage());
    // Handle the error appropriately
}
```

## 🔧 Advanced Configuration

### Custom Provider Selection
Choose providers based on your business logic:

```php
$provider = $user->country === 'EG' ? 'sms_misr' : 'twilio';

$messenger->send([
    'recipient_phone' => $user->phone_number,
    'content' => 'Message content',
    'provider' => $provider,
]);
```

### Template Integration
Use templates with your existing models:

```php
$template = Template::where('name', 'welcome_user')->first();

$messenger->sendFromTemplate($template, [
    'recipient_phone' => $user->phone_number,
    'variables' => [
        'user_name' => $user->name,
        'login_url' => url('/login'),
    ],
    'messageable_type' => User::class,
    'messageable_id' => $user->id,
]);
```

---

## ⚠️ Important Notes

1. **The Contact model is NOT required** - it's just for demo/testing
2. **Use your existing User/Member models** - the package is designed to work with them
3. **Phone number is the primary identifier** - no need for additional relationships
4. **Polymorphic relationships are optional** - use them if you need advanced features
5. **Respect user preferences** - always check opt-in status before sending messages

This approach keeps your existing data structure intact while adding powerful messaging capabilities!
