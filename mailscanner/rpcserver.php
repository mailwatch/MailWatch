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

require_once __DIR__ . '/functions.php';
ini_set('memory_limit', MEMORY_LIMIT);

function rpc_get_quarantine($msg)
{
    global $xmlrpcerruser;
    $input = php_xmlrpc_decode(array_shift($msg->params));
    if (is_string($input)) {
        $input = strtolower($input);
        $quarantinedir = get_conf_var('QuarantineDir');
        $item = array();
        $output = array();
        if ($input === '/') {

            // Return top-level directory
            $d = @opendir($quarantinedir);
            while (false !== ($f = readdir($d))) {
                if ($f !== '.' && $f !== '..') {
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
        } else {
            switch (true) {
                case(is_dir($quarantinedir . $input)):
                    $d = @opendir($quarantinedir . $input);
                    while (false !== ($f = readdir($d))) {
                        if ($f !== '.' && $f !== '..') {
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
                    return new xmlrpcresp(0, $xmlrpcerruser + 1, "$quarantinedir$input is a file.");
            }
        }
        return new xmlrpcresp(new xmlrpcval($output, 'array'));
    }

    return new xmlrpcresp(0, $xmlrpcerruser+1, __('paratype160') . ' ' . gettype($input) . ' ' . __('paratype260'));
}

function rpc_return_quarantined_file($msg)
{
    global $xmlrpcerruser;
    dbconn();
    $input = php_xmlrpc_decode(array_shift($msg->params));
    $input = preg_replace('[\.\/|\.\.\/]', '', $input);
    $date = @database::mysqli_result(
        dbquery("SELECT DATE_FORMAT(date,'%Y%m%d') FROM maillog where id='" . safe_value($input) . "'"),
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
            return new xmlrpcresp(0, $xmlrpcerruser+1, __('paratype160') . ' ' . gettype($file) . ' ' . __('paratyper260'));
        case(!is_file($quarantinedir . '/' . $file)):
            return new xmlrpcresp(0, $xmlrpcerruser+1, "$quarantinedir/$" . __('notfile60'));
        case(!is_readable($quarantinedir . '/' . $file)):
            return new xmlrpcresp(0, $xmlrpcerruser+1, "$quarantinedir/$file" . __('colon99') . ' ' . __('permdenied60'));
        default:
            $output = base64_encode(file_get_contents($quarantinedir . '/' . $file));
            break;
    }
    return new xmlrpcresp(new xmlrpcval($output, 'base64'));
}

function rpc_quarantine_list_items($msg)
{
    global $xmlrpcerruser;
    $input = php_xmlrpc_decode(array_shift($msg->params));
    if (!is_string($input)) {
        return new xmlrpcresp(0, $xmlrpcerruser+1, __('paratype160') . ' ' . gettype($input) . ' ' . __('paratyper260'));
    }
    $return = quarantine_list_items($input);
    $output = array();
    $struct = array();
    foreach ($return as $array) {
        foreach ($array as $key => $val) {
            $struct[$key] = new xmlrpcval($val);
        }
        $output[] = new xmlrpcval($struct, 'struct');
    }
    //var_dump($output);
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
    $output = shell_exec(MS_LIB_DIR . 'wrapper/sophos-wrapper /usr/local/Sophos -v');
    return new xmlrpcresp(new xmlrpcval($output, 'string'));
}

function rpc_get_conf_var($msg)
{
    global $xmlrpcerruser;
    $input = php_xmlrpc_decode(array_shift($msg->params));
    if (is_string($input)) {
        return new xmlrpcresp(new xmlrpcval(get_conf_var($input), 'string'));
    }

    return new xmlrpcresp(0, $xmlrpcerruser+1, __('paratype160') . ' ' . gettype($input) . ' ' . __('paratype260'));
}

function rpc_dump_mailscanner_conf()
{
    $fh = fopen(MS_CONFIG_DIR . 'MailScanner.conf', 'rb');
    $output = array();
    while (!feof($fh)) {
        $line = rtrim(fgets($fh, 4096));
        if (preg_match('/^([^#].+) = ([^#].*)/', $line, $regs)) {
            # Strip trailing comments
            $regs[2] = preg_replace('/#.*$/', '', $regs[2]);
            # store %var% variables
            $var = array();
            if (preg_match('/%.+%/', $regs[1])) {
                $var[$regs[1]] = $regs[2];
            }
            # expand %var% variables
            if (preg_match('/(%.+%)/', $regs[2], $match)) {
                $regs[2] = preg_replace('/%.+%/', $var[$match[1]], $regs[2]);
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
    $output = array();
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

function rpc_postfix_queues()
{
    $inq = postfixinq();
    $outq = postfixallq() - $inq;
    $result = array(
        'inq' => new xmlrpcval($inq),
        'outq' => new xmlrpcval($outq),
    );
    return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}
$xmlrpc_internalencoding = 'UTF-8';

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
            'docstring' => 'This service deletes one or more items from the quarantine.'
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
            'docstring' => 'This service returns information about the bayes database.'
        ),
        'postfix_queues' => array(
            'function' => 'rpc_postfix_queues',
            'signature' => array(array('array')),
            'docstring' => 'This service returns the number of mails in incoming/outgoing postfix queue.'
        ),
    ), false);
$s->response_charset_encoding = 'UTF-8';

// Check that the client is authorised to connect
if (is_rpc_client_allowed()) {
    $s->service();
} else {
    global $xmlrpcerruser;
    $output = new xmlrpcresp(0, $xmlrpcerruser + 1, __('client160') ." {$_SERVER['SERVER_ADDR']} " . __('client260'));
    print $output->serialize();
}
