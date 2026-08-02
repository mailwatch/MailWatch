<?php

declare(strict_types=1);

namespace MailWatch\Configuration;

final class ApiConfigurationLoader
{
    public function load(): ApiConfiguration
    {
        $apiKey = defined('API_KEY') ? constant('API_KEY') : null;
        if (!is_string($apiKey)) {
            throw new InvalidConfiguration('API_KEY must be defined as a string');
        }

        return new ApiConfiguration(
            $apiKey,
            $this->positiveIntegerConstant('API_MAX_PAYLOAD_BYTES', 10 * 1024 * 1024),
            $this->positiveIntegerConstant('API_MAX_SNAPSHOT_BYTES', 5 * 1024 * 1024),
        );
    }

    private function positiveIntegerConstant(
        string $name,
        int $default
    ): int {
        if (!defined($name)) {
            return $default;
        }

        $value = constant($name);
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new InvalidConfiguration("{$name} must be a positive integer");
        }

        $integerValue = (int)$value;
        if ($integerValue < 1) {
            throw new InvalidConfiguration("{$name} must be greater than zero");
        }

        return $integerValue;
    }
}
