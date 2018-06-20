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

class Filesystem
{
    /**
     * @return array|mixed|string
     */
    public static function getDisks()
    {
        $disks = [];
        if (PHP_OS === 'Windows NT') {
            // windows
            $disks = shell_exec('fsutil fsinfo drives');
            $disks = str_word_count($disks, 1);
            //TODO: won't work on non english installation, we need to find an universal command
            if ($disks[0] !== 'Drives') {
                return [];
            }
            unset($disks[0]);
            foreach ($disks as $disk) {
                $disks[]['mountpoint'] = $disk . ':\\';
            }
        } else {
            // unix
            /*
             * Using /proc/mounts as it seem to be standard on unix
             *
             * http://unix.stackexchange.com/a/24230/33366
             * http://unix.stackexchange.com/a/12086/33366
             */
            $temp_drive = [];
            if (is_file('/proc/mounts')) {
                $mounted_fs = file('/proc/mounts');
                foreach ($mounted_fs as $fs_row) {
                    $drive = preg_split("/[\s]+/", $fs_row);
                    if ((substr($drive[0], 0, 5) === '/dev/') && (stripos($drive[1], '/chroot/') === false)) {
                        $temp_drive['device'] = $drive[0];
                        $temp_drive['mountpoint'] = $drive[1];
                        $disks[] = $temp_drive;
                        unset($temp_drive);
                    }
                    // TODO: list nfs mount (and other relevant fs type) in $disks[]
                }
            } else {
                // fallback to mount command
                $data = shell_exec('mount');
                $data = explode("\n", $data);
                foreach ($data as $disk) {
                    $drive = preg_split("/[\s]+/", $disk);
                    if ((substr($drive[0], 0, 5) === '/dev/') && (stripos($drive[2], '/chroot/') === false)) {
                        $temp_drive['device'] = $drive[0];
                        $temp_drive['mountpoint'] = $drive[2];
                        $disks[] = $temp_drive;
                        unset($temp_drive);
                    }
                }
            }
        }

        return $disks;
    }
}
