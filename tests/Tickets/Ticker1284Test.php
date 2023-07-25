<?php

namespace App\Tests\Tickets;

use PHPUnit\Framework\TestCase;

class Ticker1284Test extends TestCase
{
    /**
     * @coversNothing
     */
    public function testItCanParseProcMounts()
    {
        foreach ($this->procmountsFixtureFiles() as $fixtureFile) {
            $disks = [];
            $mounted_fs = file($fixtureFile);
            foreach ($mounted_fs as $fs_row) {
                $drive = preg_split("/[\s]+/", $fs_row);
                if (
                    (0 === strpos($drive[0], '/dev/'))
                    && (
                        false === stripos($drive[1], '/chroot/')
                        && false === stripos($drive[1], '/snap/')
                    )
                ) {
                    $temp_drive['device'] = $drive[0];
                    $temp_drive['mountpoint'] = $drive[1];
                    $disks[] = $temp_drive;
                    unset($temp_drive);
                }
            }

            // Assert that the result is as expected.
            $this->assertNotEmpty($disks);

            // Assert that no device matches /dev/loop*
            $loopDevices = array_filter($disks, function ($disk) {
                return false !== stripos($disk['mountpoint'], 'snap');
            });
            $this->assertEmpty(
                $loopDevices,
                'There should be no /dev/loop* devices in the disks array (' . $fixtureFile . ').'
                . PHP_EOL .
                'Found: ' . var_export($loopDevices, true)
            );
        }
    }

    /**
     * @coversNothing
     */
    public function testItCanParseMountCommand()
    {
        foreach ($this->mountFixtureFiles() as $fixtureFile) {
            $disks = [];
            // mock shell_exec output
            $data = file_get_contents($fixtureFile);
            $data = explode("\n", $data);
            foreach ($data as $disk) {
                $drive = preg_split("/[\s]+/", $disk);
                if (
                    (0 === strpos($drive[0], '/dev/'))
                    && (
                        false === stripos($drive[2], '/chroot/')
                        && (false === stripos($drive[2], '/snapd/')
                        )
                    )
                ) {
                    $temp_drive['device'] = $drive[0];
                    $temp_drive['mountpoint'] = $drive[2];
                    $disks[] = $temp_drive;
                    unset($temp_drive);
                }
            }

            // Assert that the result is as expected.
            $this->assertNotEmpty($disks);

            // Assert that no device matches /dev/loop*
            $loopDevices = array_filter($disks, function ($disk) {
                return false !== stripos($disk['mountpoint'], 'snapd');
            });
            $this->assertEmpty(
                $loopDevices,
                'There should be no /dev/loop* devices in the disks array (' . $fixtureFile . ').'
                . PHP_EOL .
                'Found: ' . var_export($loopDevices, true)
            );
        }
    }

    private function procmountsFixtureFiles()
    {
        return [
            __DIR__ . '/fixtures/1284/centos_7_proc_mounts.txt',
            __DIR__ . '/fixtures/1284/ubuntu_22.04_proc_mounts.txt',
        ];
    }

    private function mountFixtureFiles()
    {
        return [
            __DIR__ . '/fixtures/1284/centos_7_mount.txt',
            __DIR__ . '/fixtures/1284/ubuntu_22.04_mount.txt',
        ];
    }
}
