<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

require_once('./functions.php');
ini_set("memory_limit", MEMORY_LIMIT);

function rpc_get_quarantine($msg)
{
    $input = php_xmlrpc_decode(array_shift($msg->params));
    if (is_string($input)) {
        $input = strtolower($input);
        $quarantinedir = get_conf_var('QuarantineDir');
        $item = array();
        $output = array();
        switch ($input) {
            case '/':
                // Return top-level directory
                $d = @opendir($quarantinedir);
                while (false !== ($f = readdir($d))) {
                    if ($f !== "." && $f !== "..") {
                        $item[] = $f;
                    }
                }
                if (count($item) > 0) {
                    // Sort in reverse chronological order
                    arsort($item);
                }
                closedir($d);
                foreach ($item as $items) {
                    $output[] = new xmlrpcval($items);
                }
                break;
            default:
                switch (true) {
                    case(is_dir($quarantinedir . $input)):
                        $d = @opendir($quarantinedir . $input);
                        while (false !== ($f = readdir($d))) {
                            if ($f !== "." && $f !== "..") {
                                $item[] = $f;
                            }
                        }
                        if (count($item) > 0) {
                            asort($item);
                        }
                        closedir($d);
                        foreach ($item as $items) {
                            $output[] = new xmlrpcval($items);
                        }
                        break;
                    case(is_file($quarantinedir . $input)):
                        return new xmlrpcresp(0, 1, "$quarantinedir$input is a file.");
                        break;
                }
                break;
        }
        return new xmlrpcresp(new xmlrpcval($output, 'array'));
    } else {
        return new xmlrpcresp(0, 1, "Parameter type " . gettype($input) . " mismatch expected type.");
    }
}

function rpc_return_quarantined_file($msg)
{
    dbconn();
    $input = php_xmlrpc_decode(array_shift($msg->params));
    $input = preg_replace('[\.\/|\.\.\/]', '', $input);
    $date = @mysql_result(
        dbquery("SELECT DATE_FORMAT(date,'%Y%m%d') FROM maillog where id='" . mysql_real_escape_string($input) . "'"),
        0
    );
    $qdir = get_conf_var('QuarantineDir');
    $file = null;
    switch (true) {
        case (file_exists($qdir . '/' . $date . '/nonspam/' . $input)):
            $file = $date . '/nonspam/' . $input;
            break;
        case (file_exists($qdir . '/' . $date . '/spam/' . $input)):
            $file = $date . '/spam/' . $input;
            break;
        case (file_exists($qdir . '/' . $date . '/mcp/' . $input)):
            $file = $date . '/mcp/' . $input;
            break;
        case (file_exists($qdir . '/' . $date . '/' . $input . '/message')):
            $file = $date . '/' . $input . '/message';
            break;
    }

    $quarantinedir = get_conf_var('QuarantineDir');
    switch (true) {
        case(!is_string($file)):
            return new xmlrpcresp(0, 1, "Parameter type " . gettype($file) . " mismatch expected type.");
        case(!is_file($quarantinedir . '/' . $file)):
            return new xmlrpcresp(0, 1, "$quarantinedir/$file is not a file.");
        case(!is_readable($quarantinedir . '/' . $file)):
            return new xmlrpcresp(0, 1, "$quarantinedir/$file: permission denied.");
        default:
            $output = base64_encode(file_get_contents($quarantinedir . '/' . $file));
            break;
    }
    return new xmlrpcresp(new xmlrpcval($output, 'base64'));
}

function rpc_quarantine_list_items($msg)
{
    $input = php_xmlrpc_decode(array_shift($msg->params));
    if (!is_string($input)) {
        return new xmlrpcresp(0, 1, "Parameter type " . gettype($input) . " mismatch expected type.");
    }
    $return = quarantine_list_items($input);
    $output = array();
    foreach ($return as $array) {
        foreach ($array as $key => $val) {
            $struct[$key] = new xmlrpcval($val);
        }
        $output[] = new xmlrpcval($struct, 'struct');
    }
    return new xmlrpcresp(new xmlrpcval($output, 'array'));
}

function rpc_quarantine_release($msg)
{
    $items = php_xmlrpc_decode(array_shift($msg->params));
    $item = php_xmlrpc_decode(array_shift($msg->params));
    $to = php_xmlrpc_decode(array_shift($msg->params));
    $return = quarantine_release($items, $item, $to);
    return new xmlrpcresp(new xmlrpcval($return, 'string'));
}

function rpc_quarantine_learn($msg)
{
    $items = php_xmlrpc_decode(array_shift($msg->params));
    $item = php_xmlrpc_decode(array_shift($msg->params));
    $type = php_xmlrpc_decode(array_shift($msg->params));
    $return = quarantine_learn($items, $item, $type);
    return new xmlrpcresp(new xmlrpcval($return, 'string'));
}

function rpc_quarantine_delete($msg)
{
    $items = php_xmlrpc_decode(array_shift($msg->params));
    $item = php_xmlrpc_decode(array_shift($msg->params));
    $return = quarantine_delete($items, $item);
    return new xmlrpcresp(new xmlrpcval($return, 'string'));
}

function rpc_sophos_status()
{
    $output = shell_exec(MS_LIB_DIR . 'sophos-wrapper /usr/local/Sophos -v');
    return new xmlrpcresp(new xmlrpcval($output, 'string'));
}

function rpc_get_conf_var($msg)
{
    $input = php_xmlrpc_decode(array_shift($msg->params));
    if (is_string($input)) {
        return new xmlrpcresp(new xmlrpcval(get_conf_var($input), 'string'));
    } else {
        return new xmlrpcresp(0, 1, "Parameter type " . gettype($input) . " mismatch expected type.");
    }
}

function rpc_dump_mailscanner_conf()
{
    $fh = fopen(MS_CONFIG_DIR . 'MailScanner.conf', 'r');
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, 4096));
        if (preg_match("/^([^#].+) = ([^#].*)/", $line, $regs)) {
            # Strip trailing comments
            $regs[2] = preg_replace("/#.*$/", "", $regs[2]);
            # store %var% variables
            if (preg_match("/%.+%/", $regs[1])) {
                $var[$regs[1]] = $regs[2];
            }
            # expand %var% variables
            if (preg_match("/(%.+%)/", $regs[2], $match)) {
                $regs[2] = preg_replace("/%.+%/", $var[$match[1]], $regs[2]);
            }
            $output[$regs[1]] = new xmlrpcval($regs[2]);
        }
    }
    fclose($fh);
    return new xmlrpcresp(new xmlrpcval($output, 'struct'));
}

function rpc_bayes_info()
{
    $fh = popen(SA_DIR . 'sa-learn -p ' . SA_PREFS . ' --dump magic', 'r');
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, 4096));
        if (preg_match('/\S+\s+\S+\s+(\S+)\s+\S+\s+non-token data: (.+)/', $line, $regs)) {
            switch ($regs[2]) {
                case 'nspam':
                    $output['Number of Spam Messages:'] = new xmlrpcval(number_format($regs[1]));
                    break;
                case 'nham':
                    $output['Number of Ham Messages:'] = new xmlrpcval(number_format($regs[1]));
                    break;
                case 'ntokens':
                    $output['Number of Tokens:'] = new xmlrpcval(number_format($regs[1]));
                    break;
                case 'oldest atime':
                    $output['Oldest Token:'] = new xmlrpcval(date('r', $regs[1]));
                    break;
                case 'newest atime':
                    $output['Newest Token:'] = new xmlrpcval(date('r', $regs[1]));
                    break;
                case 'last journal sync atime':
                    $output['Last Journal Sync:'] = new xmlrpcval(date('r', $regs[1]));
                    break;
                case 'last expiry atime':
                    $output['Last Expiry:'] = new xmlrpcval(date('r', $regs[1]));
                    break;
                case 'last expire reduction count':
                    $output['Last Expiry Reduction Count:'] = new xmlrpcval(number_format($regs[1]));
                    break;
            }
        }
    }
    return new xmlrpcresp(new xmlrpcval($output, 'struct'));
}

$s = new xmlrpc_server(array(
        'get_quarantine' => array(
            'function' => 'rpc_get_quarantine',
            'signature' => array(array('array', 'string')),
            'docstring' => 'This service returns a listing of files in the relative quarantine directory.'
        ),
        'return_quarantined_file' => array(
            'function' => 'rpc_return_quarantined_file',
            'signature' => array(array('base64', 'string')),
            'docstring' => 'This service returns the contents of a quarantined file.'
        ),
        'quarantine_list_items' => array(
            'function' => 'rpc_quarantine_list_items',
            'signature' => array(array('array', 'string')),
            'docstring' => 'This service lists the files quarantined for a given message.'
        ),
        'quarantine_release' => array(
            'function' => 'rpc_quarantine_release',
            'signature' => array(array('string', 'array', 'array', 'string')),
            'docstring' => 'This service release a message from the quarantine.'
        ),
        'quarantine_learn' => array(
            'function' => 'rpc_quarantine_learn',
            'signature' => array(array('string', 'array', 'array', 'string')),
            'docstring' => 'This service runs sa-learn on a message in the quarantine.'
        ),
        'quarantine_delete' => array(
            'function' => 'rpc_quarantine_delete',
            'signature' => array(array('string', 'array', 'array')),
            'docstring' => 'This service deltes one or more items from the quarantine.'
        ),
        'sophos_status' => array(
            'function' => 'rpc_sophos_status',
            'signature' => array(array('string')),
            'docstring' => 'This service returns the Sophos version and IDE information.'
        ),
        'get_conf_var' => array(
            'function' => 'rpc_get_conf_var',
            'signature' => array(array('string', 'string')),
            'docstring' => 'This service returns a named configuration value from MailScanner.conf.'
        ),
        'dump_mailscanner_conf' => array(
            'function' => 'rpc_dump_mailscanner_conf',
            'signature' => array(array('struct')),
            'docstring' => 'This service returns all configuration values and settings from MailScanner.conf.'
        ),
        'get_bayes_info' => array(
            'function' => 'rpc_bayes_info',
            'signature' => array(array('struct')),
            'docstring' => 'This service return information about the bayes database.'
        )
    )
    , 0);
/*
// Check that the client is authorised to connect
if(is_rpc_client_allowed()) {
    $s->service();
} else {
    $output = new xmlrpcresp(0, 1, "Client {$_SERVER['SERVER_ADDR']} is not authorized to connect.");
    print $output->serialize();
}
*/
