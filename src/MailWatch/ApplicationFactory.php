<?php

declare(strict_types=1);

namespace MailWatch;

use MailWatch\Configuration\ApiConfiguration;
use MailWatch\Configuration\ApiConfigurationLoader;

final readonly class ApplicationFactory
{
    public function __construct(
        private ApiConfiguration $apiConfiguration
    ) {
    }

    public static function create(): self
    {
        return new self((new ApiConfigurationLoader())->load());
    }

    public function apiConfiguration(): ApiConfiguration
    {
        return $this->apiConfiguration;
    }
}
