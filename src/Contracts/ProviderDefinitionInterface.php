<?php

namespace App\Messenger\Contracts;

use App\Messenger\Data\ProviderDefinition;

interface ProviderDefinitionInterface
{
    /**
     * Get provider definition for registration
     */
    public static function getProviderDefinition(): ProviderDefinition;
}
