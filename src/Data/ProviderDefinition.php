<?php

namespace App\Messenger\Data;

/**
 * Provider Definition
 *
 * Data structure for provider definitions
 */
class ProviderDefinition
{
    public function __construct(
        public string $name,
        public string $displayName,
        public string $description,
        public array $capabilities = [],
        public array $requiredConfig = [],
        public array $optionalConfig = []
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'display_name' => $this->displayName,
            'description' => $this->description,
            'capabilities' => $this->capabilities,
            'required_config' => $this->requiredConfig,
            'optional_config' => $this->optionalConfig,
        ];
    }
}
