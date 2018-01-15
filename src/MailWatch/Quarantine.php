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

namespace MailWatch;

class Quarantine
{
    /**
     * @param string $input
     * @return array
     */
    public static function quarantine_list($input = '/')
    {
        $quarantinedir = MailScanner::getConfVar('QuarantineDir') . '/';
        $item = [];
        if ($input === '/') {

            // Return top-level directory
            $d = @opendir($quarantinedir);

            while (false !== ($f = @readdir($d))) {
                if ($f !== '.' && $f !== '..') {
                    $item[] = $f;
                }
            }
            @closedir($d);
        } else {
            $current_dir = $quarantinedir . $input;
            $dirs = [$current_dir, $current_dir . '/spam', $current_dir . '/nonspam', $current_dir . '/mcp'];
            foreach ($dirs as $dir) {
                if (is_dir($dir) && is_readable($dir)) {
                    $d = @opendir($dir);
                    while (false !== ($f = readdir($d))) {
                        if ($f !== '.' && $f !== '..') {
                            $item[] = "'$f'";
                        }
                    }
                    closedir($d);
                }
            }
        }

        if (count($item) > 0) {
            // Sort in reverse chronological order
            arsort($item);
        }

        return $item;
    }

    public static function quarantine_list_items($msgid, $rpc_only = false)
    {
        $sql = "
SELECT
  hostname,
  DATE_FORMAT(date,'%Y%m%d') AS date,
  id,
  to_address,
  CASE WHEN isspam>0 THEN 'Y' ELSE 'N' END AS isspam,
  CASE WHEN nameinfected>0 THEN 'Y' ELSE 'N' END AS nameinfected,
  CASE WHEN virusinfected>0 THEN 'Y' ELSE 'N' END AS virusinfected,
  CASE WHEN otherinfected>0 THEN 'Y' ELSE 'N' END AS otherinfected
 FROM
  maillog
 WHERE
  id = '$msgid'";
        $sth = Db::query($sql);
        $rows = $sth->num_rows;
        if ($rows <= 0) {
            die(__('diequarantine103') . " $msgid " . __('diequarantine103') . "\n");
        }
        $row = $sth->fetch_object();
        if (!$rpc_only && is_local($row->hostname)) {
            $quarantinedir = MailScanner::getConfVar('QuarantineDir');
            $quarantine = $quarantinedir . '/' . $row->date . '/' . $row->id;
            $spam = $quarantinedir . '/' . $row->date . '/spam/' . $row->id;
            $nonspam = $quarantinedir . '/' . $row->date . '/nonspam/' . $row->id;
            $mcp = $quarantinedir . '/' . $row->date . '/mcp/' . $row->id;
            $infected = 'N';
            if ($row->virusinfected === 'Y' || $row->nameinfected === 'Y' || $row->otherinfected === 'Y') {
                $infected = 'Y';
            }
            $quarantined = [];
            $count = 0;
            foreach ([$nonspam, $spam, $mcp] as $category) {
                if (file_exists($category) && is_readable($category)) {
                    $quarantined[$count]['id'] = $count;
                    $quarantined[$count]['host'] = $row->hostname;
                    $quarantined[$count]['msgid'] = $row->id;
                    $quarantined[$count]['to'] = $row->to_address;
                    $quarantined[$count]['file'] = 'message';
                    $quarantined[$count]['type'] = 'message/rfc822';
                    $quarantined[$count]['path'] = $category;
                    $quarantined[$count]['md5'] = md5($category);
                    $quarantined[$count]['dangerous'] = $infected;
                    $quarantined[$count]['isspam'] = $row->isspam;
                    $count++;
                }
            }
            // Check the main quarantine
            if (is_dir($quarantine) && is_readable($quarantine)) {
                $d = opendir($quarantine) or die(__('diequarantine303') . " $quarantine\n");
                while (false !== ($f = readdir($d))) {
                    if ($f !== '..' && $f !== '.') {
                        $quarantined[$count]['id'] = $count;
                        $quarantined[$count]['host'] = $row->hostname;
                        $quarantined[$count]['msgid'] = $row->id;
                        $quarantined[$count]['to'] = $row->to_address;
                        $quarantined[$count]['file'] = $f;
                        $file = escapeshellarg($quarantine . '/' . $f);
                        $quarantined[$count]['type'] = ltrim(rtrim(`/usr/bin/file -bi $file`));
                        $quarantined[$count]['path'] = $quarantine . '/' . $f;
                        $quarantined[$count]['md5'] = md5($quarantine . '/' . $f);
                        $quarantined[$count]['dangerous'] = $infected;
                        $quarantined[$count]['isspam'] = $row->isspam;
                        $count++;
                    }
                }
                closedir($d);
            }

            return $quarantined;
        }

        // Host is remote call quarantine_list_items by RPC
        \MailWatch\Debug::debug("Calling quarantine_list_items on $row->hostname by XML-RPC");
        //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$row->hostname,80);
        //if(DEBUG) { $client->setDebug(1); }
        //$parameters = array($input);
        //$msg = new xmlrpcmsg('quarantine_list_items',$parameters);
        $msg = new \xmlrpcmsg('quarantine_list_items', [new \xmlrpcval($msgid)]);
        $rsp = xmlrpc_wrapper($row->hostname, $msg); //$client->send($msg);
        if ($rsp->faultCode() === 0) {
            $response = php_xmlrpc_decode($rsp->value());
        } else {
            $response = 'XML-RPC Error: ' . $rsp->faultString();
        }

        return $response;
    }

    public static function quarantine_release($list, $num, $to, $rpc_only = false)
    {
        if (!is_array($list) || !isset($list[0]['msgid'])) {
            return 'Invalid argument';
        }

        $new = Quarantine::quarantine_list_items($list[0]['msgid']);
        $list =& $new;

        if (!$rpc_only && is_local($list[0]['host'])) {
            if (!QUARANTINE_USE_SENDMAIL) {
                $hdrs = ['From' => MAILWATCH_FROM_ADDR, 'Subject' => \ForceUTF8\Encoding::toUTF8(QUARANTINE_SUBJECT), 'Date' => date('r')];
                $mailMimeParams = [
                    'eol' => "\r\n",
                    'html_charset' => 'UTF-8',
                    'text_charset' => 'UTF-8',
                    'head_charset' => 'UTF-8'
                ];
                $mime = new Mail_mime($mailMimeParams);
                $mime->setTXTBody(\ForceUTF8\Encoding::toUTF8(QUARANTINE_MSG_BODY));
                // Loop through each selected file and attach them to the mail
                foreach ($num as $key => $val) {
                    // If the message is of rfc822 type then set it as Quoted printable
                    if (preg_match('/message\/rfc822/', $list[$val]['type'])) {
                        $mime->addAttachment($list[$val]['path'], 'message/rfc822', 'Original Message', true, '');
                    } else {
                        // Default is base64 encoded
                        $mime->addAttachment($list[$val]['path'], $list[$val]['type'], $list[$val]['file'], true);
                    }
                }
                $mail_param = ['host' => MAILWATCH_MAIL_HOST];
                $body = $mime->get();
                $hdrs = $mime->headers($hdrs);
                $mail = new Mail_smtp($mail_param);

                $m_result = $mail->send($to, $hdrs, $body);
                if (is_a($m_result, 'PEAR_Error')) {
                    // Error
                    $status = __('releaseerror03') . ' (' . $m_result->getMessage() . ')';
                    global $error;
                    $error = true;
                } else {
                    $sql = "UPDATE maillog SET released = '1' WHERE id = '" .  Sanitize::safe_value($list[0]['msgid']) . "'";
                    Db::query($sql);
                    $status = __('releasemessage03') . ' ' . str_replace(',', ', ', $to);
                    Security::audit_log(sprintf(__('auditlogquareleased03', true), $list[0]['msgid']) . ' ' . $to);
                }

                return $status;
            }

            // Use sendmail to release message
            // We can only release message/rfc822 files in this way.
            $cmd = QUARANTINE_SENDMAIL_PATH . ' -i -f ' . MAILWATCH_FROM_ADDR . ' ' . escapeshellarg($to) . ' < ';
            foreach ($num as $key => $val) {
                if (preg_match('/message\/rfc822/', $list[$val]['type'])) {
                    Debug::debug($cmd . $list[$val]['path']);
                    exec($cmd . $list[$val]['path'] . ' 2>&1', $output_array, $retval);
                    if ($retval === 0) {
                        $sql = "UPDATE maillog SET released = '1' WHERE id = '" .  Sanitize::safe_value($list[0]['msgid']) . "'";
                        Db::query($sql);
                        $status = __('releasemessage03') . ' ' . str_replace(',', ', ', $to);
                        Security::audit_log(sprintf(__('auditlogquareleased03', true), $list[$val]['msgid']) . ' ' . $to);
                    } else {
                        $status = __('releaseerrorcode03') . ' ' . $retval . ' ' . __('returnedfrom03') . "\n" . implode(
                                "\n",
                                $output_array
                            );
                        global $error;
                        $error = true;
                    }

                    return $status;
                }
            }
        } else {
            // Host is remote - handle by RPC
            Debug::debug('Calling quarantine_release on ' . $list[0]['host'] . ' by XML-RPC');
            //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
            // Convert input parameters
            $list_output = [];
            foreach ($list as $list_array) {
                $list_struct = [];
                foreach ($list_array as $key => $val) {
                    $list_struct[$key] = new \xmlrpcval($val);
                }
                $list_output[] = new \xmlrpcval($list_struct, 'struct');
            }
            $num_output = [];
            foreach ($num as $key => $val) {
                $num_output[$key] = new \xmlrpcval($val);
            }
            // Build input parameters
            $param1 = new \xmlrpcval($list_output, 'array');
            $param2 = new \xmlrpcval($num_output, 'array');
            $param3 = new \xmlrpcval($to, 'string');
            $parameters = [$param1, $param2, $param3];
            $msg = new \xmlrpcmsg('quarantine_release', $parameters);
            $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
            if ($rsp->faultCode() === 0) {
                $response = php_xmlrpc_decode($rsp->value());
            } else {
                $response = 'XML-RPC Error: ' . $rsp->faultString();
            }

            return $response . ' (RPC)';
        }
    }

    public static function quarantine_learn($list, $num, $type, $rpc_only = false)
    {
        Db::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if (!is_array($list) || !isset($list[0]['msgid'])) {
            return 'Invalid argument';
        }
        $new = Quarantine::quarantine_list_items($list[0]['msgid']);
        $list =& $new;
        $status = [];
        if (!$rpc_only && is_local($list[0]['host'])) {
            //prevent sa-learn process blocking complete apache server
            session_write_close();
            foreach ($num as $key => $val) {
                $use_spamassassin = false;
                $isfn = '0';
                $isfp = '0';
                switch ($type) {
                    case 'ham':
                        $learn_type = 'ham';
                        // Learning SPAM as HAM - this is a false-positive
                        $isfp = ($list[$val]['isspam'] === 'Y' ? '1' : '0');
                        break;
                    case 'spam':
                        $learn_type = 'spam';
                        // Learning HAM as SPAM - this is a false-negative
                        $isfn = ($list[$val]['isspam'] === 'N' ? '1' : '0');
                        break;
                    case 'forget':
                        $learn_type = 'forget';
                        break;
                    case 'report':
                        $use_spamassassin = true;
                        $learn_type = '-r';
                        $isfn = '1';
                        break;
                    case 'revoke':
                        $use_spamassassin = true;
                        $learn_type = '-k';
                        $isfp = '1';
                        break;
                    default:
                        //TODO handle this case
                        $isfp = null;
                }
                if ($isfp !== null) {
                    $sql = 'UPDATE maillog SET isfp=' . $isfp . ', isfn=' . $isfn . " WHERE id='"
                        .  Sanitize::safe_value($list[$val]['msgid']) . "'";
                }

                if (true === $use_spamassassin) {
                    // Run SpamAssassin to report or revoke spam/ham
                    exec(
                        SA_DIR . 'spamassassin -p ' . SA_PREFS . ' ' . $learn_type . ' < ' . $list[$val]['path'] . ' 2>&1',
                        $output_array,
                        $retval
                    );
                    if ($retval === 0) {
                        // Command succeeded - update the database accordingly
                        if (isset($sql)) {
                            Debug::debug("Learner - running SQL: $sql");
                            Db::query($sql);
                        }
                        $status[] = __('spamassassin03') . ' ' . implode(', ', $output_array);
                        switch ($learn_type) {
                            case '-r':
                                $learn_type = 'spam';
                                break;
                            case '-k':
                                $learn_type = 'ham';
                                break;
                        }
                        Security::audit_log(
                            sprintf(__('auditlogquareleased03', true) . ' ', $list[$val]['msgid']) . ' ' . $learn_type
                        );
                    } else {
                        $status[] = __('spamerrorcode0103') . ' ' . $retval . __('spamerrorcode0203') . "\n" . implode(
                                "\n",
                                $output_array
                            );
                        global $error;
                        $error = true;
                    }
                } else {
                    // Only sa-learn required
                    $max_size_option = '';
                    if (defined('SA_MAXSIZE') && is_int(SA_MAXSIZE) && SA_MAXSIZE > 0) {
                        $max_size_option = ' --max-size ' . SA_MAXSIZE;
                    }

                    exec(
                        SA_DIR . 'sa-learn -p ' . SA_PREFS . ' --' . $learn_type . ' --file ' . $list[$val]['path'] . $max_size_option . ' 2>&1',
                        $output_array,
                        $retval
                    );

                    if ($retval === 0) {
                        // Command succeeded - update the database accordingly
                        if (isset($sql)) {
                            Debug::debug("Learner - running SQL: $sql");
                            Db::query($sql);
                        }
                        $status[] = __('salearn03') . ' ' . implode(', ', $output_array);
                        Security::audit_log(sprintf(__('auditlogspamtrained03', true), $list[$val]['msgid']) . ' ' . $learn_type);
                    } else {
                        $status[] = __('salearnerror03') . ' ' . $retval . ' ' . __('salearnreturn03') . "\n" . implode(
                                "\n",
                                $output_array
                            );
                        global $error;
                        $error = true;
                    }
                }
                if (!isset($error)) {
                    if ($learn_type === 'spam') {
                        $numeric_type = 2;
                    }
                    if ($learn_type === 'ham') {
                        $numeric_type = 1;
                    }
                    if (isset($numeric_type)) {
                        $sql = "UPDATE `maillog` SET salearn = '$numeric_type' WHERE id = '" .  Sanitize::safe_value($list[$val]['msgid']) . "'";
                        Db::query($sql);
                    }
                }
            }

            return implode("\n", $status);
        }

        // Call by RPC
        Debug::debug('Calling quarantine_learn on ' . $list[0]['host'] . ' by XML-RPC');
        //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
        // Convert input parameters
        $list_output = [];
        foreach ($list as $list_array) {
            $list_struct = [];
            foreach ($list_array as $key => $val) {
                $list_struct[$key] = new \xmlrpcval($val);
            }
            $list_output[] = new \xmlrpcval($list_struct, 'struct');
        }
        $num_output = [];
        foreach ($num as $key => $val) {
            $num_output[$key] = new \xmlrpcval($val);
        }
        // Build input parameters
        $param1 = new \xmlrpcval($list_output, 'array');
        $param2 = new \xmlrpcval($num_output, 'array');
        $param3 = new \xmlrpcval($type, 'string');
        $parameters = [$param1, $param2, $param3];
        $msg = new \xmlrpcmsg('quarantine_learn', $parameters);
        $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
        if ($rsp->faultCode() === 0) {
            $response = php_xmlrpc_decode($rsp->value());
        } else {
            $response = 'XML-RPC Error: ' . $rsp->faultString();
        }

        return $response . ' (RPC)';
    }

    public static function quarantine_delete($list, $num, $rpc_only = false)
    {
        if (!is_array($list) || !isset($list[0]['msgid'])) {
            return 'Invalid argument';
        }

        $new = Quarantine::quarantine_list_items($list[0]['msgid']);
        $list =& $new;

        if (!$rpc_only && is_local($list[0]['host'])) {
            $status = [];
            foreach ($num as $key => $val) {
                if (@unlink($list[$val]['path'])) {
                    $status[] = 'Delete: deleted file ' . $list[$val]['path'];
                    \MailWatch\Db::query("UPDATE maillog SET quarantined=NULL WHERE id='" . $list[$val]['msgid'] . "'");
                    \MailWatch\Security::audit_log(__('auditlogdelqua03', true) . ' ' . $list[$val]['path']);
                } else {
                    $status[] = __('auditlogdelerror03') . ' ' . $list[$val]['path'];
                    global $error;
                    $error = true;
                }
            }

            return implode("\n", $status);
        }

        // Call by RPC
        \MailWatch\Debug::debug('Calling quarantine_delete on ' . $list[0]['host'] . ' by XML-RPC');
        //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$list[0]['host'],80);
        // Convert input parameters
        $list_output = [];
        foreach ($list as $list_array) {
            $list_struct = [];
            foreach ($list_array as $key => $val) {
                $list_struct[$key] = new \xmlrpcval($val);
            }
            $list_output[] = new \xmlrpcval($list_struct, 'struct');
        }
        $num_output = [];
        foreach ($num as $key => $val) {
            $num_output[$key] = new \xmlrpcval($val);
        }
        // Build input parameters
        $param1 = new \xmlrpcval($list_output, 'array');
        $param2 = new \xmlrpcval($num_output, 'array');
        $parameters = [$param1, $param2];
        $msg = new \xmlrpcmsg('quarantine_delete', $parameters);
        $rsp = xmlrpc_wrapper($list[0]['host'], $msg); //$client->send($msg);
        if ($rsp->faultCode() === 0) {
            $response = php_xmlrpc_decode($rsp->value());
        } else {
            $response = 'XML-RPC Error: ' . $rsp->faultString();
        }

        return $response . ' (RPC)';
    }

    public static function return_quarantine_dates()
    {
        $array = [];
        for ($d = 0; $d < QUARANTINE_DAYS_TO_KEEP; $d++) {
            $array[] = date('Ymd', mktime(0, 0, 0, date('m'), date('d') - $d, date('Y')));
        }

        return $array;
    }
}
