<?php

namespace Ihabrouk\Messenger\Contracts;

use Ihabrouk\Messenger\Data\ProviderDefinition;

interface ProviderDefinitionInterface
{
    /**
     * Get provider definition for registration
     */
    public static function getProviderDefinition(): ProviderDefinition;
}
