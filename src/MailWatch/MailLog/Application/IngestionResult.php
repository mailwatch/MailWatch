<?php

declare(strict_types=1);

namespace MailWatch\MailLog\Application;

enum IngestionResult
{
    case Inserted;
    case Duplicate;
}
