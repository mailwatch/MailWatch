<?php

declare(strict_types=1);

namespace MailWatch\Shared\Http;

enum RouteType
{
    /** A JSON endpoint used by the MailScanner modules. */
    case Api;

    /** A page served by a controller. */
    case Controller;

    /** A page script that has not been extracted yet. */
    case Page;

    /** A path that has moved, answered with a permanent redirect. */
    case Redirect;
}
