<?php

namespace App\Messenger\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeDriverCommand extends Command
{
    protected $signature = 'messenger:make-driver {name : The name of the driver}';
    protected $description = 'Create a new messenger driver';

    public function handle()
    {
        $name = $this->argument('name');
        $className = Str::studly($name) . 'Driver';
        $filename = $className . '.php';
        $path = app_path("Messenger/Drivers/{$filename}");

        if (file_exists($path)) {
            $this->error("Driver {$className} already exists!");
            return 1;
        }

        $stub = $this->getStub();
        $content = str_replace([
            '{{className}}',
            '{{driverName}}',
        ], [
            $className,
            strtolower($name),
        ], $stub);

        file_put_contents($path, $content);

        $this->info("Driver {$className} created successfully!");
        $this->line("Location: {$path}");
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Implement the required methods in your driver');
        $this->line('2. Add the driver configuration to config/messenger.php');
        $this->line('3. Register the driver in MessageProviderFactory');

        return 0;
    }

    protected function getStub(): string
    {
        return <<<'STUB'
<?php

namespace App\Messenger\Drivers;

use App\Messenger\Contracts\MessageProviderInterface;
use App\Messenger\Data\SendMessageData;
use App\Messenger\Data\MessageResponse;
use App\Messenger\Enums\MessageStatus;
use App\Messenger\Enums\MessageType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class {{className}} implements MessageProviderInterface
{
    protected Client $client;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new Client([
            'timeout' => $config['timeout'] ?? 30,
            'verify' => true,
        ]);
    }

    public function send(SendMessageData $data): MessageResponse
    {
        try {
            // Implement your send logic here
            $response = $this->client->post($this->config['api_url'], [
                'json' => $this->buildPayload($data),
                'headers' => $this->buildHeaders(),
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            return $this->parseResponse($responseData);
        } catch (RequestException $e) {
            return MessageResponse::failure(
                'HTTP_ERROR',
                $e->getMessage(),
                '{{driverName}}'
            );
        }
    }

    public function sendBulk(array $messages): array
    {
        $results = [];

        foreach ($messages as $message) {
            $results[] = $this->send($message);
        }

        return $results;
    }

    public function getBalance(): float
    {
        // Implement balance check
        return 0.0;
    }

    public function verifyWebhook(string $payload, string $signature): bool
    {
        // Implement webhook verification
        return false;
    }

    public function processWebhook(array $payload): array
    {
        // Implement webhook processing
        return [];
    }

    public function getName(): string
    {
        return '{{driverName}}';
    }

    public function getSupportedTypes(): array
    {
        return [MessageType::SMS];
    }

    public function getMaxRecipients(): int
    {
        return $this->config['max_recipients'] ?? 1000;
    }

    public function isHealthy(): bool
    {
        try {
            // Implement health check
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function buildPayload(SendMessageData $data): array
    {
        // Build the API payload
        return [
            'to' => $data->to,
            'message' => $data->message,
            // Add other required fields
        ];
    }

    protected function buildHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            // Add authentication headers
        ];
    }

    protected function parseResponse(array $response): MessageResponse
    {
        // Parse the provider response
        if (isset($response['success']) && $response['success']) {
            return MessageResponse::success(
                $response['id'] ?? '',
                $response['message_id'] ?? null,
                $response['cost'] ?? null,
                '{{driverName}}'
            );
        }

        return MessageResponse::failure(
            $response['error_code'] ?? 'UNKNOWN_ERROR',
            $response['error_message'] ?? 'Unknown error occurred',
            '{{driverName}}'
        );
    }
}
STUB;
    }
}
