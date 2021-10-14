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

// Include of necessary functions
require_once __DIR__ . '/filter.inc.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/graphgenerator.inc.php';

// Authentication checking
require __DIR__ . '/login.function.php';

// add the header information such as the logo, search, menu, ....
$filter = html_start(__('toprecipdomvol41'), 0, false, true);

$graphgenerator = new GraphGenerator();
$graphgenerator->sqlQuery = '
 SELECT
  to_domain AS `name`,
  COUNT(*) as `count`,
  SUM(size) as `size`
 FROM
  maillog
 WHERE
  from_address <> "" 		-- Exclude delivery receipts
 AND
  from_address IS NOT NULL     	-- Exclude delivery receipts
' . $filter->CreateSQL() . '
 GROUP BY
  to_domain
 ORDER BY
  size DESC
 LIMIT 10
';

$graphgenerator->tableColumns = [
    'name' => __('domain41'),
    'countconv' => __('count03'),
    'sizeconv' => __('size03')
];
$graphgenerator->sqlColumns = [
    'name',
    'count',
    'size'
];
$graphgenerator->valueConversion = [
    'size' => 'scale',
    'count' => 'number'
];
$graphgenerator->graphColumns = [
    'labelColumn' => 'name',
    'dataNumericColumn' => 'size',
    'dataFormattedColumn' => 'sizeconv'
];
$graphgenerator->graphTitle = __('top10recipdomvol41');
$graphgenerator->printPieGraph();

// Add footer
html_end();
// Close any open db connections
dbclose();
