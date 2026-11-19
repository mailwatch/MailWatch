<?php

/*
 * MailWatch for MailScanner
 * Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 * Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)
 * Copyright (C) 2014-2026  MailWatch Team (https://github.com/mailwatch/1.2.0/graphs/contributors)
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
 * Software Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

/**
 * Items of a quarantine_list_items() answer of another node.
 *
 * The node is not trusted for what the master already knows: host, message id, recipients and flags come from
 * the maillog row, so that the answer cannot point the master to its own files or to another recipient.
 *
 * @param mixed  $response  decoded XML-RPC answer
 * @param object $row       maillog row with hostname, id, to_address and isspam
 * @param string $dangerous Y when the maillog row is infected
 *
 * @return array|string the items, or an error message
 */
function quarantine_remote_items($response, $row, $dangerous)
{
    $invalid = 'XML-RPC Error: invalid response';
    if (!is_array($response)) {
        return $invalid;
    }

    $items = [];
    // the positions are kept: the node finds the items to handle by their index in its own list
    foreach (array_values($response) as $index => $item) {
        if (!is_array($item)) {
            return $invalid;
        }
        $remote = [];
        foreach (['file', 'type', 'path', 'md5'] as $key) {
            if (!isset($item[$key]) || !is_string($item[$key])) {
                return $invalid;
            }
            $remote[$key] = $item[$key];
        }
        $items[] = [
            'id' => $index,
            'host' => $row->hostname,
            'msgid' => $row->id,
            'to' => $row->to_address,
            'file' => $remote['file'],
            'type' => $remote['type'],
            'path' => $remote['path'],
            'md5' => $remote['md5'],
            'dangerous' => $dangerous,
            'isspam' => $row->isspam,
        ];
    }

    return $items;
}
