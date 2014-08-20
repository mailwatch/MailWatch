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
require_once('Mail/mimeDecode.php');
ini_set("memory_limit", MEMORY_LIMIT);

session_start();
require('login.function.php');

html_start("Message Viewer", 0, false, false);
?>
    <SCRIPT type="application/javascript">
        <!--
        function do_action(id, action) {
            ActionString = "quarantine_action.php?id=" + id + "&action=" + action + "&html=true";
            DoActionWindow = window.open(ActionString, '', 'toolbar=no, directories=no, location=no, status=no, menubar=no, resizable=no, scrollbars=no, width=900, height=150');
        }
        -->
    </SCRIPT>
<?php
dbconn();
if (!isset($_GET['id'])) {
    die("No input Message ID");
} else {
    $sql = "SELECT * FROM maillog WHERE id='" . mysql_real_escape_string($_GET['id']) . "' AND " . $_SESSION["global_filter"] . "";
    $row = @mysql_fetch_object(dbquery($sql));
    // See if message is local
    if (empty($row)) {
        die("Message '" . $_GET['id'] . "' not found\n");
    } else {
        audit_log('Quarantined message (' . $_GET['id'] . ') body viewed');
    }
    $using_rpc = false;
    if (!is_local($row->hostname) || RPC_ONLY) {
        // Host is remote - use XML-RPC
        $using_rpc = true;
        //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$row->hostname,80);
        $input = new xmlrpcval($_GET['id']);
        $parameters = array($input);
        $msg = new xmlrpcmsg('return_quarantined_file', $parameters);
        //$rsp = $client->send($msg);
        $rsp = xmlrpc_wrapper($row->hostname, $msg);
        if ($rsp->faultcode() == 0) {
            $response = php_xmlrpc_decode($rsp->value());
        } else {
            die("Error: " . $rsp->faultstring());
        }
        $file = base64_decode($response);
    } else {
        // If filename input not present - work out path
        $date = @mysql_result(dbquery("SELECT DATE_FORMAT(date,'%Y%m%d') FROM maillog where id='" . mysql_real_escape_string($_GET['id']) . "'"), 0);
        $qdir = get_conf_var('QuarantineDir');
        switch (true) {
            case (file_exists($qdir . '/' . $date . '/nonspam/' . $_GET['id'])):
                $_GET['filename'] = $date . '/nonspam/' . $_GET['id'];
                break;
            case (file_exists($qdir . '/' . $date . '/spam/' . $_GET['id'])):
                $_GET['filename'] = $date . '/spam/' . $_GET['id'];
                break;
            case (file_exists($qdir . '/' . $date . '/mcp/' . $_GET['id'])):
                $_GET['filename'] = $date . '/mcp/' . $_GET['id'];
                break;
            case (file_exists($qdir . '/' . $date . '/' . $_GET['id'] . '/message')):
                $_GET['filename'] = $date . '/' . $_GET['id'] . '/message';
                break;
        }

        // File is local
        if (!isset($_GET['filename'])) {
            die("No input filename");
        } else {
            // SECURITY - strip off any potential nasties
            $_GET['filename'] = preg_replace('[\.\/|\.\.\/]', '', $_GET['filename']);
            $filename = get_conf_var('QuarantineDir') . "/" . $_GET['filename'];
            if (!@file_exists($filename)) {
                die("Error: file not found\n");
            }
            $file = file_get_contents($filename);
        }
    }
}

$params['include_bodies'] = false;
$params['decode_bodies'] = true;
$params['decode_headers'] = true;
$params['input'] = $file;

$Mail_mimeDecode = new Mail_mimeDecode($file);
$structure = $Mail_mimeDecode->decode($params);
$mime_struct = $Mail_mimeDecode->getMimeNumbers($structure);

echo "<table border=0 cellspacing=1 cellpadding=1 class=\"maildetail\" width=100%>\n";
echo " <thead>\n";
if ($using_rpc) {
    $title = "Message Viewer: " . $_GET['id'] . " on " . $row->hostname;
} else {
    $title = "Message Viewer: " . $_GET['id'];
}
echo "  <tr>\n";
echo "    <th colspan=2>$title</th>\n";
echo "  </tr>\n";
echo " </thead>\n";

function lazy($title, $val, $dohtmlentities=true)
{
    if ($dohtmlentities) {
      $v = htmlentities($val);
    } else {
      $v = $val;
    }
    echo ' <tr>
   <td class="heading" align="right" width="10%">' . $title . '</td>
   <td class="detail" width="80%">' . $v . '</td>
   </tr>' . "\n";
}

// Display the headers
switch (true) {
    case isset($structure->headers['date']):
        if (function_exists('mb_check_encoding')) {
            if (!mb_check_encoding($structure->headers['date'], 'UTF-8')) {
                $structure->headers['date'] = mb_convert_encoding($structure->headers['date'], 'UTF-8');
            }
        } else {
            $structure->headers['date'] = utf8_encode($structure->headers['date']);
        }
        lazy("Date:", $structure->headers['date']);
    case isset($structure->headers['from']):
        if (function_exists('mb_check_encoding')) {
            if (!mb_check_encoding($structure->headers['from'], 'UTF-8')) {
                $structure->headers['from'] = mb_convert_encoding($structure->headers['from'], 'UTF-8');
            }
        } else {
            $structure->headers['from'] = utf8_encode($structure->headers['from']);
        }
        lazy("From:", str_replace('"', '', $structure->headers['from']));
    case isset($structure->headers['to']):
        if (function_exists('mb_check_encoding')) {
            if (!mb_check_encoding($structure->headers['to'], 'UTF-8')) {
                $structure->headers['to'] = mb_convert_encoding($structure->headers['to'], 'UTF-8');
            }
        } else {
            $structure->headers['to'] = utf8_encode($structure->headers['to']);
        }
        lazy("To:", str_replace('"', '', $structure->headers['to']));
    case isset($structure->headers['subject']):
        if (function_exists('mb_check_encoding')) {
            if (!mb_check_encoding($structure->headers['subject'], 'UTF-8')) {
                $structure->headers['subject'] = mb_convert_encoding($structure->headers['subject'], 'UTF-8');
            }
        } else {
            $structure->headers['subject'] = utf8_encode($structure->headers['subject']);
        }
        lazy("Subject:", $structure->headers['subject']);
}

if (($row->virusinfected == 0 && $row->nameinfected == 0 && $row->otherinfected == 0) || $_SESSION['user_type'] == 'A') {
    lazy(
        "Actions:",
        "<a href=\"javascript:void(0)\" onClick=\"do_action('" . $row->id . "','release')\">Release this message</a> | <a href=\"javascript:void(0)\" onClick=\"do_action('" . $row->id . "','delete')\">Delete this message</a>",
        false
    );
}

foreach ($mime_struct as $key => $part) {
    $type = $part->ctype_primary . '/' . $part->ctype_secondary;
    echo " <tr>\n";
    echo "  <td colspan=2 class=\"heading\">MIME Type: $type</td>\n";

    switch ($type) {
        case "text/plain":
        case "text/html":
            echo " <tr>\n";
            echo "  <td colspan=2>\n";
            echo "   <iframe frameborder=0 width=\"100%\" height=300 src=\"viewpart.php?id=" . $_GET['id'] . "&amp;filename=" . $_GET['filename'] . "&amp;part=" . $part->mime_id . "\"></iframe>\n";
            echo "  </td>\n";
            echo " </tr>\n";
            break;
        case "message/rfc822":
            break;
        case "multipart/related":
            break;
        case "multipart/alternative":
            break;
        default:
            echo " <tr>\n";

            echo "  <td colspan=2 class=\"detail\">" . $part->d_parameters['filename'];
            if (($row->virusinfected == 0 && $row->nameinfected == 0 && $row->otherinfected == 0) || $_SESSION['user_type'] == 'A') {
                echo " <a href=\"viewpart.php?id=" . $_GET['id'] . "&amp;filename=" . $_GET['filename'] . "&amp;part=" . $part->mime_id . "\">Download</a>";
            }
            echo "  </td>";

            echo " </tr>\n";
            break;
    }
}

echo "</table>\n";

// Add footer
html_end();
// Close any open db connections
dbclose();
