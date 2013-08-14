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

session_start();
require('login.function.php');

ini_set("memory_limit",MEMORY_LIMIT);

if(!isset($_GET['id'])) {
 die("No input Message ID");
} else {
 // See if message is local
 if(!($host = @mysql_result(dbquery("SELECT hostname FROM maillog WHERE id='".mysql_real_escape_string($_GET['id'])."' AND ".$_SESSION["global_filter"].""),0))) {
  die("Message '".$_GET['id']."' not found\n");
 }
 if(!is_local($host) || RPC_ONLY) {
  // Host is remote - use XML-RPC
  //$client = new xmlrpc_client(constant('RPC_RELATIVE_PATH').'/rpcserver.php',$host,80);
  $input = new xmlrpcval($_GET['id']);
  $parameters = array($input);
  $msg = new xmlrpcmsg('return_quarantined_file',$parameters);
  //$rsp = $client->send($msg);
  $rsp = xmlrpc_wrapper($host,$msg);
  if($rsp->faultcode()==0) {
   $response = php_xmlrpc_decode($rsp->value());
  } else {
   die("Error: ".$rsp->faultstring());
  }
  $file = base64_decode($response);
 } else {
  $date = @mysql_result(dbquery("SELECT DATE_FORMAT(date,'%Y%m%d') FROM maillog where id='".mysql_real_escape_string($_GET['id'])."' AND ".$_SESSION["global_filter"].""),0);
  $qdir = get_conf_var('QuarantineDir');
  switch(true) {
   case (file_exists($qdir.'/'.$date.'/nonspam/'.$_GET['id'])):
    $_GET['filename'] = $date.'/nonspam/'.$_GET['id'];
    break;
   case (file_exists($qdir.'/'.$date.'/spam/'.$_GET['id'])):
    $_GET['filename'] = $date.'/spam/'.$_GET['id'];
    break;
   case (file_exists($qdir.'/'.$date.'/mcp/'.$_GET['id'])):
    $_GET['filename'] = $date.'/mcp/'.$_GET['id'];
    break;
   case (file_exists($qdir.'/'.$date.'/'.$_GET['id'].'/message')):
    $_GET['filename'] = $date.'/'.$_GET['id'].'/message';
    break;
  }

  // File is local
  if(!isset($_GET['filename'])) {
   die("No input filename");
  } else {
   // SECURITY - strip off any potential nasties
   $_GET['filename'] = preg_replace('[\.\/|\.\.\/]','',$_GET['filename']);
   $filename = get_conf_var('QuarantineDir')."/".$_GET['filename'];
   if(!@file_exists($filename)) {
    die("Error: file not found\n");
   }
   $file = file_get_contents($filename);
  }
 }
}

$params['include_bodies'] = true;
$params['decode_bodies'] = true;
$params['decode_headers'] = true;
$params['input'] = $file;

$structure = Mail_mimeDecode::decode($params);
$mime_struct = Mail_mimeDecode::getMimeNumbers($structure);

// Make sure that part being requested actually exists
if(isset($_GET['part'])) {
 if(!isset($mime_struct[$_GET['part']])) {
  die("Part ".$_GET['part']." not found\n");
 }
}

function decode_structure($structure) {
 $type = $structure->ctype_primary."/".$structure->ctype_secondary;
 switch($type) {
  case "text/plain":
   if (isset ($structure->ctype_parameters['charset']) && strtolower($structure->ctype_parameters['charset']) == 'utf-8') {
     $structure->body = utf8_decode ($structure->body);
   }
   echo '<html>
 <head>
 <title>Quarantined E-Mail Viewer</title>
 </head>
 <body>
 <pre>
 '.htmlentities(wordwrap($structure->body)).'
 </pre>
 </body>
 </html>'."\n";
   break;
  case "text/html":
   if (isset ($structure->ctype_parameters['charset']) && strtolower($structure->ctype_parameters['charset']) != 'utf-8') {
    $structure->body = utf8_encode ($structure->body);
   }
   if(STRIP_HTML) {
    echo strip_tags($structure->body, ALLOWED_TAGS);
   } else {
    echo $structure->body;
   }
   break;
  case "multipart/alternative":
   break;
  default:
   header("Content-type: ".$structure->headers['content-type']);
   header("Content-Disposition: ".$structure->headers['content-disposition']);
   echo $structure->body;
   break;
 }
}

decode_structure($mime_struct[$_GET['part']]);

// Close any open db connections
dbclose();
