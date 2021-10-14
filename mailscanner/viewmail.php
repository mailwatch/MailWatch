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
ini_set('memory_limit', MEMORY_LIMIT);

require __DIR__ . '/login.function.php';

html_start(__('msgviewer06'), 0, false, false);
?>
    <SCRIPT type="application/javascript">
        <!--
        function do_action(id, token, action) {
            ActionString = "quarantine_action.php?token=" + token + "&id=" + id + "&action=" + action + "&html=true";
            DoActionWindow = window.open(ActionString, '', 'toolbar=no, directories=no, location=no, status=no, menubar=no, resizable=no, scrollbars=no, width=900, height=150');
        }
        -->
    </SCRIPT>
<?php
dbconn();
if (!isset($_GET['id']) && !isset($_GET['amp;id'])) {
    die(__('nomessid06'));
}
if (isset($_GET['amp;id'])) {
    $message_id = deepSanitizeInput($_GET['amp;id'], 'url');
} else {
    $message_id = deepSanitizeInput($_GET['id'], 'url');
}
if (!validateInput($message_id, 'msgid')) {
    die();
}
$sql = "SELECT * FROM maillog WHERE id='" . $message_id . "' AND " . $_SESSION['global_filter'];
$result = dbquery($sql);
$message = $result->fetch_object();
// See if message is local
if (empty($message)) {
    die(__('mess06') . " '" . $message_id . "' " . __('notfound06') . "\n");
}

audit_log(sprintf(__('auditlog06', true), $message_id));

if ($message->token !== deepSanitizeInput($_GET['token'], 'url') && false === checkToken($_GET['token'])) {
    header('Location: login.php?error=pagetimeout');
    die();
}

$using_rpc = false;
if (RPC_ONLY || !is_local($message->hostname)) {
    // Host is remote - use XML-RPC
    $using_rpc = true;
    //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$row->hostname,80);
    $input = new xmlrpcval($message_id);
    $parameters = [$input];
    $msg = new xmlrpcmsg('return_quarantined_file', $parameters);
    //$rsp = $client->send($msg);
    $rsp = xmlrpc_wrapper($message->hostname, $msg);
    if ($rsp->faultCode() === 0) {
        $response = php_xmlrpc_decode($rsp->value());
    } else {
        die(__('error06') . ' ' . $rsp->faultString());
    }
    $file = base64_decode($response);
} else {
    //build filename path
    $date = DateTime::createFromFormat('Y-m-d', $message->date)->format('Ymd');
    $quarantine_dir = get_conf_var('QuarantineDir');
    $filename = '';
    switch (true) {
        case (file_exists($quarantine_dir . '/' . $date . '/nonspam/' . $message_id)):
            $filename = $date . '/nonspam/' . $message_id;
            break;
        case (file_exists($quarantine_dir . '/' . $date . '/spam/' . $message_id)):
            $filename = $date . '/spam/' . $message_id;
            break;
        case (file_exists($quarantine_dir . '/' . $date . '/mcp/' . $message_id)):
            $filename = $date . '/mcp/' . $message_id;
            break;
        case (file_exists($quarantine_dir . '/' . $date . '/' . $message_id . '/message')):
            $filename = $date . '/' . $message_id . '/message';
            break;
    }

    if (!@file_exists($quarantine_dir . '/' . $filename)) {
        die(__('errornfd06') . "\n");
    }
    $file = file_get_contents($quarantine_dir . '/' . $filename);
}

$params['include_bodies'] = false;
$params['decode_bodies'] = true;
$params['decode_headers'] = 'UTF8//TRANSLIT/IGNORE';
$params['input'] = $file;

$Mail_mimeDecode = new Mail_mimeDecode($file);
$structure = $Mail_mimeDecode->decode($params);
$mime_struct = $Mail_mimeDecode->getMimeNumbers($structure);

echo '<table border="0" cellspacing="1" cellpadding="1" class="maildetail" width="100%">' . "\n";
echo " <thead>\n";
if ($using_rpc) {
    $title = __('msgviewer06') . __('colon99') . ' ' . $message_id . ' on ' . $message->hostname;
} else {
    $title = __('msgviewer06') . __('colon99') . ' ' . $message_id;
}
echo "  <tr>\n";
echo "    <th colspan=2>$title</th>\n";
echo "  </tr>\n";
echo " </thead>\n";

function lazy($title, $val, $dohtmlentities = true)
{
    $v = $val;
    if ($dohtmlentities) {
        $v = htmlentities($v);
    }
    $titleintl = $title;
    switch ($title) {
        case 'Date:':
            $titleintl = __('date06');
            break;
        case 'From:':
            $titleintl = __('from06');
            break;
        case 'To:':
            $titleintl = __('to06');
            break;
        case 'Subject:':
            $titleintl = __('subject06');
            break;
    }
    echo ' <tr>
   <td class="heading" align="right" width="10%">' . $titleintl . '</td>
   <td class="detail" width="80%">' . $v . '</td>
   </tr>' . "\n";
}

// Display the headers
$header_fields = [
    ['name' => 'date', 'replaceQuote' => false],
    ['name' => 'from', 'replaceQuote' => true],
    ['name' => 'to', 'replaceQuote' => true],
    ['name' => 'subject', 'replaceQuote' => false],
];

foreach ($header_fields as $field) {
    if (isset($structure->headers[$field['name']])) {
        /* this is a quick hack to fix issue #154, This need to be recoded in next version */
        if (is_array($structure->headers[$field['name']])) {
            $structure->headers[$field['name']] = implode('; ', $structure->headers[$field['name']]);
        }
        $structure->headers[$field['name']] = htmlspecialchars(getUTF8String(decode_header($structure->headers[$field['name']])));
        if ($field['replaceQuote']) {
            $structure->headers[$field['name']] = str_replace('"', '', $structure->headers[$field['name']]);
        }
        lazy(ucfirst($field['name']) . ':', $structure->headers[$field['name']], false);
    }
}

if (
        ($message->virusinfected === '0' && $message->nameinfected === '0' && $message->otherinfected === '0') ||
        $_SESSION['user_type'] === 'A' ||
        (defined('DOMAINADMIN_CAN_SEE_DANGEROUS_CONTENTS') && true === DOMAINADMIN_CAN_SEE_DANGEROUS_CONTENTS && $_SESSION['user_type'] === 'D')
) {
    lazy(
        __('actions06'),
        "<a href=\"javascript:void(0)\" onclick=\"do_action('" . $message->id . "','" . $_SESSION['token'] . "','release')\">" . __('releasemsg06') . "</a> | <a href=\"javascript:void(0)\" onclick=\"do_action('" . $message->id . "','" . $_SESSION['token'] . "','delete')\">" . __('deletemsg06') . '</a>',
        false
    );
}

foreach ($mime_struct as $key => $part) {
    $type = isset($part->ctype_primary) ? $part->ctype_primary : 'undefined';
    $type .= '/';
    $type .= isset($part->ctype_secondary) ? $part->ctype_secondary : 'undefined';
    
    echo ' <tr>' . "\n";
    echo '  <td colspan=2 class="heading">' . __('mymetype06') . ' ' . $type . '</td>' . "\n";

    switch ($type) {
        case 'text/plain':
        case 'text/html':
            echo ' <tr>' . "\n";
            echo '  <td colspan="2">' . "\n";
            echo '   <iframe frameborder=0 width="100%" height=300 src="viewpart.php?token=' . $_SESSION['token'] .'&amp;id=' . $message_id . '&amp;part=' . $part->mime_id . '"></iframe>' . "\n";
            echo '  </td>' . "\n";
            echo ' </tr>' . "\n";
            break;
        case 'message/rfc822':
            break;
        case 'multipart/related':
            break;
        case 'multipart/alternative':
            break;
        default:
            echo ' <tr>' . "\n";
            echo '  <td colspan=2 class="detail">';

            if (property_exists($part, 'd_parameters')) {
                if (isset($part->d_parameters['filename'])) {
                    echo $part->d_parameters['filename'];
                } else {
                    echo __('nonameattachment06');
                }
                if (isset($part->d_parameters['size'])) {
                    echo '&nbsp;(size ' . formatSize($part->d_parameters['size']) . ')';
                }
            } else {
                $filename = __('nonameattachment06');
                if ($type = 'message/partial' && property_exists($part, 'ctype_parameters')) {
                    $filename = isset($part->ctype_parameters['id']) ? $part->ctype_parameters['id'] : 'partialMessage';
                    if (isset($part->ctype_parameters['number'])) {
                        $filename .= ' - Part ' . $part->ctype_parameters['number'];
                    }
                    if (isset($part->ctype_parameters['total'])) {
                        $filename .= ' of ' . $part->ctype_parameters['total'];
                    }
                    $filename .= '.bin';
                }
                echo $filename;
            }

            if (
                ($message->virusinfected === '0' && $message->nameinfected === '0' && $message->otherinfected === '0') ||
                $_SESSION['user_type'] === 'A' ||
                (defined('DOMAINADMIN_CAN_SEE_DANGEROUS_CONTENTS') && true === DOMAINADMIN_CAN_SEE_DANGEROUS_CONTENTS && $_SESSION['user_type'] === 'D')
            ) {
                echo ' <a href="viewpart.php?token=' . $_SESSION['token'] . '&amp;id=' . $message_id . '&amp;part=' . $part->mime_id . '">Download</a>';
            }

            echo '  </td>';

            echo ' </tr>' . "\n";
            break;
    }
}

echo '</table>' . "\n";

// Add footer
html_end();
// Close any open db connections
dbclose();
