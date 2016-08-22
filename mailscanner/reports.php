<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2016  MailWatch Team (https://github.com/orgs/mailwatch/teams/team-stable)
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
 * As a special exception, you have permission to link this program with the JpGraph library and distribute executables,
 * as long as you follow the requirements of the GNU GPL in regard to all of the software in the executable aside from
 * JpGraph.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

//Require files
require_once(__DIR__ . '/functions.php');
require_once(__DIR__ . '/filter.inc.php');

// verify login
session_start();
require(__DIR__ . '/login.function.php');

// Checking to see if there are any filters
if (!isset($_SESSION["filter"]) || !is_object($_SESSION["filter"])) {
    $filter = new Filter;
    $_SESSION["filter"] = $filter;
} else {
    $filter = $_SESSION["filter"];
}

// add the header information such as the logo, search, menu, ....
html_start("Reports", "0", false, false);

// Add filters and save them
if (isset($_GET["action"])) {
    switch (strtolower(sanitizeInput($_GET["action"]))) {
        case "add":
            $filter->Add(sanitizeInput($_GET["column"]), $_GET["operator"], sanitizeInput($_GET["value"]));
            break;
        case "remove":
            $filter->Remove(sanitizeInput($_GET["column"]));
            break;
        case "destroy":
            session_destroy();
            echo "Session destroyed\n";
            exit;
        case "save":
            if (isset($_GET['save_as'])) {
                $name = sanitizeInput($_GET['save_as']);
            }
            if (isset($_GET['filter']) && $_GET['filter'] != "_none_") {
                $name = sanitizeInput($_GET['filter']);
            }
            if (!empty($name)) {
                $filter->Save($name);
            }
            break;
        case "load":
            $filter->Load(sanitizeInput($_GET['filter']));
            break;
        case "delete":
            $filter->Delete(sanitizeInput($_GET['filter']));
            break;
    }
}

// add the session filters to the variables
$_SESSION["filter"] = $filter;

$filter->AddReport("rep_message_listing.php", __('messlistin14'));
$filter->AddReport("rep_message_ops.php", __('messop14'));

$filter->AddReport("rep_total_mail_by_date.php", __('messdate14'));
$filter->AddReport("rep_top_mail_relays.php", __('topmailrelay14'));

$filter->AddReport("rep_top_viruses.php", __('topvirus14'));
$filter->AddReport("rep_viruses.php", __('virusrepor14'));

$filter->AddReport("rep_top_senders_by_quantity.php", __('topsendersqt14'));
$filter->AddReport("rep_top_senders_by_volume.php", __('topsendersvol14'));
$filter->AddReport("rep_top_recipients_by_quantity.php", __('toprecipqt14'));
$filter->AddReport("rep_top_recipients_by_volume.php", __('toprecipvol14'));

//$filter->AddReport("rep_mrtg_style.php","__('mrtgreport14'));

$filter->AddReport("rep_top_sender_domains_by_quantity.php", __('topsendersdomqt14'));
$filter->AddReport("rep_top_sender_domains_by_volume.php", __('topsendersdomqt14'));
$filter->AddReport("rep_top_recipient_domains_by_quantity.php", __('toprecipdomqt14'));
$filter->AddReport("rep_top_recipient_domains_by_volume.php", __('toprecipdomvol14'));

if (get_conf_truefalse('UseSpamAssassin') === true) {
    $filter->AddReport("rep_sa_score_dist.php", __('assassinscoredist14'));
    $filter->AddReport("rep_sa_rule_hits.php", __('assassinrulhit14'));
}
if (get_conf_truefalse('MCPChecks') === true) {
    $filter->AddReport("rep_mcp_score_dist.php", __('mcpscoredist14'));
    $filter->AddReport("rep_mcp_rule_hits.php", __('mcprulehit14'));
}

$filter->AddReport("rep_audit_log.php", __('auditlog14'));
$filter->Display();

clear_cache_dir();

// Add footer
html_end();
// Close any open db connections
dbclose();
