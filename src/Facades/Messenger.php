<?php

namespace Ihabrouk\Messenger\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Ihabrouk\Messenger\Data\MessageResponse send(array $data)
 * @method static \Ihabrouk\Messenger\Data\MessageResponse sendFromTemplate(string $templateName, array $data)
 * @method static array bulkSend(\Ihabrouk\Messenger\Models\Batch $batch, array $recipients)
 * @method static bool scheduleMessage(array $data, \Carbon\Carbon $scheduledAt)
 * @method static bool cancelMessage(int $messageId)
 * @method static array getProviderHealth()
 *
 * @see \Ihabrouk\Messenger\Services\MessengerService
 */
class Messenger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Ihabrouk\Messenger\Contracts\MessengerServiceInterface::class;
    }
}
