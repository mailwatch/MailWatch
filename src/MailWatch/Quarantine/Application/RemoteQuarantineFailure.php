<?php

declare(strict_types=1);

namespace MailWatch\Quarantine\Application;

/**
 * The other node could not be reached, or refused the operation.
 */
final class RemoteQuarantineFailure extends \RuntimeException
{
}
