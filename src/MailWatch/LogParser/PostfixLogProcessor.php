<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2018  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
 *
 * In addition, as a special exception, the copyright holder gives permission to link the code of this program with
 * those files in the PEAR library that are licensed under the PHP License (or with modified versions of those files
 * that use the same license as those files), and distribute linked combinations including the two.
 * You must obey the GNU General Public License in all respects for all of the code used other than those files in the
 * PEAR library that are licensed under the PHP License. If you modify this program, you may extend this exception to
 * your version of the program, but you are not obligated to do so.
 * If you do not wish to do so, delete this exception statement from your version.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace MailWatch\LogParser;

class PostfixLogProcessor extends MtaLogProcessor
{
    public function __construct()
    {
        $this->mtaprocess = 'postfix/smtp';
        $this->delayField = 'delay';
        $this->statusField = 'status';
    }

    public function getRejectReasons()
    {
        // you can use these matches to populate your table with all the various reject reasons etc., so one could get stats about MTA rejects as well
        // example
        $rejectReasons = [];
        if (false !== stripos($this->entry, 'NOQUEUE')) {
            if (preg_match('/Client host rejected: cannot find your hostname/i', $this->entry)) {
                $rejectReasons['type'] =  \MailWatch\Sanitize::safe_value('unknown_hostname');
            } else {
                $rejectReasons['type'] =  \MailWatch\Sanitize::safe_value('NOQUEUE');
            }
            $rejectReasons['status'] =  \MailWatch\Sanitize::safe_value($this->raw);
        }

        return $rejectReasons;
    }

    public function extractKeyValuePairs($match)
    {
        $entries = [];
        $pattern = '/to=<(?<to>[^>]*)>, (?:orig_to=<(?<orig_to>[^>]*)>, )?relay=(?<relay>[^,]+), (?:conn_use=(?<conn_use>[^,])+, )?delay=(?<delay>[^,]+), (?:delays=(?<delays>[^,]+), )?(?:dsn=(?<dsn>[^,]+), )?status=(?<status>.*)$/';
        preg_match($pattern, $match[2], $entries);

        return $entries;
    }
}
