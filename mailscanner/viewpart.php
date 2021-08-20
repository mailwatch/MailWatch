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
require_once __DIR__ . '/lib/pear/Mail/mimeDecode.php';

require __DIR__ . '/login.function.php';

ini_set('memory_limit', MEMORY_LIMIT);

if (!isset($_GET['id'])) {
    die(__('nomessid58'));
}

if (false === checkToken($_GET['token'])) {
    header('Location: login.php?error=pagetimeout');
    die();
}

$message_id = deepSanitizeInput($_GET['id'], 'url');
if (!validateInput($message_id, 'msgid')) {
    die(__('dievalidate99'));
}
// See if message is local
dbconn(); // required db link for mysql_real_escape_string
$result = dbquery(
    "SELECT hostname, DATE_FORMAT(date,'%Y%m%d') AS date FROM maillog WHERE id='" .
    $message_id . "' AND "
    . $_SESSION['global_filter']
);
$message_data = $result->fetch_object();

if (!$message_data) {
    die(__('mess58') . " '" . $message_id . "' " . __('notfound58') . "\n");
}

if (RPC_ONLY || !is_local($message_data->hostname)) {
    // Host is remote - use XML-RPC
    //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php', $host, 80);
    $input = new xmlrpcval($message_id);
    $parameters = [$input];
    $msg = new xmlrpcmsg('return_quarantined_file', $parameters);
    //$rsp = $client->send($msg);
    $rsp = xmlrpc_wrapper($message_data->hostname, $msg);
    if ($rsp->faultCode() === 0) {
        $response = php_xmlrpc_decode($rsp->value());
    } else {
        die(__('error58') . ' ' . $rsp->faultString());
    }
    $file = base64_decode($response);
} else {
    //build filename path
    $quarantine_dir = get_conf_var('QuarantineDir');
    $filename = '';
    switch (true) {
        case (file_exists($quarantine_dir . '/' . $message_data->date . '/nonspam/' . $message_id)):
            $filename = $message_data->date . '/nonspam/' . $message_id;
            break;
        case (file_exists($quarantine_dir . '/' . $message_data->date . '/spam/' . $message_id)):
            $filename = $message_data->date . '/spam/' . $message_id;
            break;
        case (file_exists($quarantine_dir . '/' . $message_data->date . '/mcp/' . $message_id)):
            $filename = $message_data->date . '/mcp/' . $message_id;
            break;
        case (file_exists($quarantine_dir . '/' . $message_data->date . '/' . $message_id . '/message')):
            $filename = $message_data->date . '/' . $message_id . '/message';
            break;
    }

    if (!@file_exists($quarantine_dir . '/' . $filename)) {
        die(__('errornfd58') . "\n");
    }
    $file = file_get_contents($quarantine_dir . '/' . $filename);
}

$params['include_bodies'] = true;
$params['decode_bodies'] = 'UTF8//TRANSLIT/IGNORE';
$params['decode_headers'] = true;
$params['input'] = $file;

$Mail_mimeDecode = new Mail_mimeDecode($file);
$structure = $Mail_mimeDecode->decode($params);
$mime_struct = $Mail_mimeDecode->getMimeNumbers($structure);

if (isset($_GET['part'])) {
    $part = deepSanitizeInput($_GET['part'], 'url');
    if (!validateInput($part, 'mimepart')) {
        die(__('dievalidate99'));
    }

    // Make sure that part being requested actually exists
    if (!isset($mime_struct[$part])) {
        die(__('part58') . ' ' . $part . ' ' . __('notfound58') . "\n");
    }
} else {
    die(__('part58') . __('notfound58') . "\n");
}

/**
 * @param stdClass $structure a Mail_mimeDecode structure object
 */
function decode_structure($structure)
{
    $type = $structure->ctype_primary . '/' . $structure->ctype_secondary;
    switch ($type) {
        case 'text/plain':
            /*
            if (isset ($structure->ctype_parameters['charset']) &&
                strtolower($structure->ctype_parameters['charset']) == 'utf-8'
            ) {
                $structure->body = utf8_decode($structure->body);
            }
            */
            if (isset($structure->ctype_parameters['charset'])) {
                if (strtolower($structure->ctype_parameters['charset']) == 'windows-1255') {
                    $structure->body = iconv('ISO-8859-8', 'UTF-8', $structure->body);
                } elseif (strtolower($structure->ctype_parameters['charset']) !== 'utf-8') {
                    $structure->body = utf8_encode($structure->body);
                }
            }
            echo '<!DOCTYPE html>
 <html>
 <head>
 <meta charset="utf-8">
 <title>' . __('title58') . '</title>
 </head>
 <body>
 <pre>' . htmlspecialchars(wordwrap($structure->body)) . '</pre>
 </body>
 </html>' . "\n";
            break;
        case 'text/html':
            echo '<!DOCTYPE html>' . "\n";
            if (isset($structure->ctype_parameters['charset'])) {
                if (strtolower($structure->ctype_parameters['charset']) == 'windows-1255') {
                    $structure->body = iconv('ISO-8859-8', 'UTF-8', $structure->body);
                } elseif (strtolower($structure->ctype_parameters['charset']) !== 'utf-8') {
                    $structure->body = utf8_encode($structure->body);
                }
            }
            if (STRIP_HTML) {
                $structure->body = str_replace('<!DOCTYPE', '<DOCTYPE', $structure->body);
                echo strip_tags($structure->body, ALLOWED_TAGS);
            } else {
                echo $structure->body;
            }
            break;
        case 'multipart/alternative':
            break;
        case 'message/partial':
            // @link https://tools.ietf.org/html/rfc2046#section-5.2.2
            header('Content-Type: application/octet-stream');
            //get message id
            preg_match('/.*id="?([^";]*)"?.*/', $structure->headers['content-type'], $identifier);
            //get part number
            preg_match("/.*number=([\d]*).*/", $structure->headers['content-type'], $partNumber);
            //get total parts
            preg_match("/.*total=([\d]*).*/", $structure->headers['content-type'], $totalParts);

            //build filename
            $filename = isset($identifier[1]) ? $identifier[1] : 'partialMessage';
            if (isset($partNumber[1])) {
                $filename .= ' - Part ' . $partNumber[1];
            }
            if (isset($totalParts[1])) {
                $filename .= ' of ' . $totalParts[1];
            }
            $filename .= '.bin';

            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $structure->body;
            break;
        default:
            header('Content-Type: ' . $structure->headers['content-type']);
            // in case of missing Content-Disposition use a standard one
            if (isset($structure->headers['content-disposition'])) {
                header('Content-Disposition: ' . $structure->headers['content-disposition']);
            } else {
                header('Content-Disposition: attachment; filename="attachment.bin"');
            }
            echo $structure->body;
            break;
    }
}

decode_structure($mime_struct[$part]);

// Close any open db connections
dbclose();
