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

use MailWatch\Db;
use MailWatch\Sanitize;
use MailWatch\Translation;

abstract class MtaLogProcessor
{
    protected $mtaprocess;
    protected $delayField;
    protected $statusField;

    protected $raw;
    protected $id;
    protected $entry;
    protected $entries;

    /**
     * @param array $match
     * @return array
     */
    abstract public function extractKeyValuePairs($match);

    /**
     * @return array
     */
    public function getRejectReasons()
    {
        return [];
    }

    /**
     * @return array
     */
    public function getRulesets()
    {
        return [];
    }

    public function doit($input)
    {
        global $fp; //@todo do we need this?
        if (!$fp = popen($input, 'r')) {
            die(Translation::__('diepipe56'));
        }
        Db::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        $lines = 1;
        while ($line = fgets($fp, 2096)) {
            // Reset variables
            unset($parsed, $_timestamp, $_host, $_type, $_msg_id, $_status);

            $parsed = new SyslogParser($line);
            $_timestamp = Sanitize::safe_value($parsed->timestamp);
            $_host = Sanitize::safe_value($parsed->host);
            $_dsn = '';
            $_delay = '';
            $_relay = '';
            $_msg_id = '';
            $_status = '';

            if ($parsed->process === $this->mtaprocess) {
                $this->parse($parsed->entry);
                if (true === DEBUG) {
                    print_r($this);
                }

                $_msg_id = Sanitize::safe_value($this->id);

                //apply rulesets if they exist
                $rulesets = $this->getRulesets();
                if (isset($rulesets['type'])) {
                    $_type = $rulesets['type'];
                }
                if (isset($rulesets['relay'])) {
                    $_relay = $rulesets['relay'];
                }
                if (isset($rulesets['status'])) {
                    $_status = $rulesets['status'];
                }

                // Milter-ahead rejections
                if (preg_match('/Milter: /i', $this->raw) && preg_match(
                        '/(rejected recipient|user unknown)/i',
                        $this->entries['reject']
                    )
                ) {
                    $_type = Sanitize::safe_value('unknown_user');
                    $_status = Sanitize::safe_value($this->getEmail($this->entries['to']));
                }

                // Unknown users
                if (preg_match('/user unknown/i', $this->entry)) {
                    // Unknown users
                    $_type = Sanitize::safe_value('unknown_user');
                    $_status = Sanitize::safe_value($this->raw);
                }

                //apply reject reasons if they exist
                $rejectReasons = $this->getRejectReasons();
                if (isset($rejectReasons['type'])) {
                    $_type = $rejectReasons['type'];
                }
                if (isset($rejectReasons['status'])) {
                    $_status = $rejectReasons['status'];
                }

                // Relay lines
                if (isset($this->entries['relay'], $this->entries[$this->statusField])) {
                    $_type = Sanitize::safe_value('relay');
                    $_delay = Sanitize::safe_value($this->entries[$this->delayField]);
                    $_relay = Sanitize::safe_value($this->getIp());
                    $_dsn = Sanitize::safe_value($this->entries['dsn']);
                    $_status = Sanitize::safe_value($this->entries[$this->statusField]);
                }
            }
            if (isset($_type)) {
                Db::query(
                    "REPLACE INTO mtalog (`timestamp`,`host`,`type`,`msg_id`,`relay`,`dsn`,`status`,`delay`) VALUES (FROM_UNIXTIME('$_timestamp'),'$_host','$_type','$_msg_id','$_relay','$_dsn','$_status',SEC_TO_TIME('$_delay'))"
                );
            }
            $lines++;
        }
        Db::close();
        pclose($fp);
    }

    /**
     * @param string $line
     * @return bool
     */
    public function parse($line)
    {
        //reset the variables
        $this->id = null;
        $this->entry = null;
        $this->entries = null;
        $this->raw = $line;

        //do the parse
        if (preg_match('/^(\S+):\s(.+)$/', $line, $match)) {
            $this->id = $match[1];

            // Milter
            if (preg_match('/(\S+):\sMilter:\s(.+)$/', $line, $milter)) {
                $match = $milter;
            }

            // Extract any key=value pairs
            if (false !== strpos($match[2], '=')) {
                //calls the function passed as argument
                $this->entries = $this->extractKeyValuePairs($match);
            } else {
                $this->entry = $match[2];
            }

            return true;
        }

        // No message ID found
        // Extract any key=value pairs
        if (false !== strpos($this->raw, '=')) {
            $items = explode(', ', $this->raw);
            $entries = [];
            foreach ($items as $item) {
                $entry = explode('=', $item);
                // fix for the id= issue 09.12.2011
                if (isset($entry[2])) {
                    $entries[$entry[0]] = $entry[1] . '=' . $entry[2];
                } elseif (isset($entry[1])) {
                    $entries[$entry[0]] = $entry[1];
                }
                // ignore cases where a part after ',' does not contain '=' (see issue #1021)
            }
            $this->entries = $entries;

            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getIp()
    {
        if (preg_match('/\[(\d+\.\d+\.\d+\.\d+)\]/', $this->entries['relay'], $match)) {
            return $match[1];
        }

        return $this->entries['relay'];
    }

    /**
     * @param string $entry
     * @return string
     */
    public function getEmail($entry)
    {
        if (preg_match('/<(\S+)>/', $entry, $match)) {
            return $match[1];
        }

        return $entry;
    }
}
