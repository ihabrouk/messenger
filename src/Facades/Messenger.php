<?php

namespace Ihabrouk\Messenger\Facades;

use Ihabrouk\Messenger\Contracts\MessengerServiceInterface;
use Ihabrouk\Messenger\Data\MessageResponse;
use Ihabrouk\Messenger\Models\Batch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Facade;

/**
 * @method static MessageResponse send(array $data)
 * @method static MessageResponse sendFromTemplate(string $templateName, array $data)
 * @method static array bulkSend(Batch $batch, array $recipients)
 * @method static bool scheduleMessage(array $data, Carbon $scheduledAt)
 * @method static bool cancelMessage(int $messageId)
 * @method static array getProviderHealth()
 *
 * @see \Ihabrouk\Messenger\Services\MessengerService
 */
class Messenger extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MessengerServiceInterface::class;
    }
}
