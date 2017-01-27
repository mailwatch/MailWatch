<?php 
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
        } else {
            return false;
        }
    }
}

/**
 * @param string $line
 * @return string
 */
function get_ip($line)
{
    if (preg_match('/\[(\d+\.\d+\.\d+\.\d+)\]/', $line, $match)) {
        return $match[1];
    } else {
        return $line;
    }
}

/**
 * @param string $line
 * @return string
 */
function get_email($line)
{
    if (preg_match('/<(\S+)>/', $line, $match)) {
        return $match[1];
    } else {
        return $line;
    }
}


class MtaLogProcessor {
    $mtaprocess;
    $delayField;
    $statusField;
    
    $raw;
    $id;
    $entry;
    $entries;

    abstract function extractKeyValuePairs($match);
    
    function getRejectReasons() {
        return array();
    }
    
    function getRulesets() {
        return array();
    }
    
    function doit($input)
    {
        global $fp;//TODO do we need this?
        if (!$fp = popen($input, 'r')) {
            die(__('diepipe56')); //TODO remember to change the mapping of ...56 to this file
        }
        dbconn();

        $lines = 1;
        while ($line = fgets($fp, 2096)) {        
            // Reset variables
            unset($parsed, $mta_parser, $_timestamp, $_host, $_type, $_msg_id, $_status);
            
            $parsed = new SyslogParser($line);
            $_timestamp = safe_value($parsed->timestamp);
            $_host = safe_value($parsed->host);
            $_dsn = '';
            $_delay = '';
            $_relay = '';

            if ($parsed->process === $this->mtaprocess) {
                $this->parse($parsed->entry);
                if (true === DEBUG) {
                    print_r($this);
                }

                $_msg_id = safe_value($this->id);
                
                //apply rulesets if they exist
                $rulesets = getRulesets();
                if(isset($rulesets['type'])) {
                    $_type = $rulesets['type'];
                }
                if(isset($rulesets['relay'])) {
                    $_relay = $rulesets['relay'];
                }
                if(isset($rulesets['status'])) {
                    $_status = $rulesets['status'];
                }

                // Milter-ahead rejections
                if (preg_match('/Milter: /i', $this->raw) && preg_match(
                        '/(rejected recipient|user unknown)/i',
                        $this->entries['reject']
                    )
                ) {
                    $_type = safe_value('unknown_user');
                    $_status = safe_value(get_email($this->entries['to']));
                }

                // Unknown users
                if (preg_match('/user unknown/i', $this->entry)) {
                    // Unknown users
                    $_type = safe_value('unknown_user');
                    $_status = safe_value($this->raw);
                }
                
                //apply reject reasons if they exist
                $rejectReasons = getRejectReasons();
                if(isset($rejectReasons['type'])) {
                    $_type = $rejectReasons['type'];
                }
                if(isset($rejectReasons['status'])) {
                    $_status = $rejectReasons['status'];
                }

                // Relay lines
                if (isset($this->entries['relay'], $this->entries[$statusField])) {
                    $_type = safe_value('relay');
                    $_delay = safe_value($this->entries[$this->delayField]);
                    $_relay = safe_value(get_ip($this->entries['relay']));
                    $_dsn = safe_value($this->entries['dsn']);
                    $_status = safe_value($this->entries[$this->statusField]);
                }
            }
            if (isset($_type)) {
                dbquery(
                    "REPLACE INTO mtalog VALUES (FROM_UNIXTIME('$_timestamp'),'$_host','$_type','$_msg_id','$_relay','$_dsn','$_status','$_delay')"
                );
            }
            $lines++;
        }
        dbclose();
        pclose($fp);
    }

    /**
     * @param string $line
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
            if (strstr($match[2], '=')) {
                //calls the function passed as argument
                $this->entries = $this->extractKeyValuePairs($match);
            } else {
                $this->entry = $match[2];
            }
        } else {
            // No message ID found
            // Extract any key=value pairs
            if (strstr($this->raw, '=')) {
                $items = explode(', ', $this->raw);
                $entries = array();
                foreach ($items as $item) {
                    $entry = explode('=', $item);
                    // fix for the id= issue 09.12.2011
                    if (isset($entry[2])) {
                        $entries[$entry[0]] = $entry[1] . '=' . $entry[2];
                    } else {
                        $entries[$entry[0]] = $entry[1];
                    }
                }
                $this->entries = $entries;
            } else {
                return false;
            }
        }
    }
}