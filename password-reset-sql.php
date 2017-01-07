<?php
/**
 * Created by PhpStorm.
 * User: Alan Urquhart
 * Company: ASU Web Services LTD
 * Web: www.asuweb.co.uk
 * Date: 07/01/2017
 * Time: 09:55
 *
 * Created for Mailwatch 1.2.0 RC4
 */
header("Content-type: text/plain\n\n");
require("/var/www/html/mailwatch-development/functions.php");

$link = dbconn();
echo "Testing DB Connection...";
if($link) {
    echo "OK...\n";
    echo "Updating users table for password-reset...";
    $sql = 'ALTER TABLE users ADD COLUMN (
resetid varchar(255),
resetexpire bigint(20),
lastreset bigint(20)
);';
    $result = dbquery($sql);
    if(!$result) echo "\nERROR Failed to modify table ".$link->error;
    else echo "done\n";
    echo "Finished\n";
}
else echo "DB Connection Failed..";
