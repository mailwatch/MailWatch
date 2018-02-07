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
 
class SyslogParser
{
    public $raw;
    public $timestamp;
    public $date;
    public $time;
    public $rfctime;
    public $host;
    public $process;
    public $pid;
    public $entry;
    public $months = array(
        'Jan' => '1',
        'Feb' => '2',
        'Mar' => '3',
        'Apr' => '4',
        'May' => '5',
        'Jun' => '6',
        'Jul' => '7',
        'Aug' => '8',
        'Sep' => '9',
        'Oct' => '10',
        'Nov' => '11',
        'Dec' => '12'
    );

    /**
     * @param string $line
     */
    public function __construct($line)
    {
        // Parse the date, time, host, process pid and log entry
        if (preg_match('/^(\S+)\s+(\d+)\s(\d+):(\d+):(\d+)\s(\S+)\s(\S+)\[(\d+)\]:\s(.+)$/', $line, $explode)) {
            // Store raw line
            $this->raw = $explode[0];

            // Decode the syslog time/date
            $month = $this->months[$explode[1]];
            $thismonth = date('n');
            $thisyear = date('Y');
            // Work out the year
            $year = $month <= $thismonth ? $thisyear : $thisyear - 1;
            $this->date = $explode[2] . ' ' . $explode[1] . ' ' . $year;
            $this->time = $explode[3] . ':' . $explode[4] . ':' . $explode[5];
            $datetime = $this->date . ' ' . $this->time;
            $this->timestamp = strtotime($datetime);
            $this->rfctime = date('r', $this->timestamp);

            $this->host = $explode[6];
            $this->process = $explode[7];
            $this->pid = $explode[8];
            $this->entry = $explode[9];
        }
    }
}
