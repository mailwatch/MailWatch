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

use GuzzleHttp\Client;
use MaxMind\Db\Reader;
use MaxMind\Db\Reader\InvalidDatabaseException;

class GeoIp
{
    public $download = [
        'database' => 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.tar.gz',
        'md5' => 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.tar.gz.md5',
    ];

    public static $savePath = [
        'database' => __DIR__ . '/../../data/geoipdb/GeoLite2-Country.tar.gz',
        'md5' => __DIR__ . '/../../data/geoipdb/GeoLite2-Country.tar.gz.md5',
        'extractTo' => __DIR__ . '/../../data/geoipdb/',
        'mmdbFile' => __DIR__ . '/../../data/geoipdb/GeoLite2-Country.mmdb',
    ];

    /**
     * @param string $ip
     *
     * @return bool
     */
    public static function getCountry($ip): bool
    {
        if (file_exists(self::$savePath['mmdbFile']) && filesize(self::$savePath['mmdbFile']) > 0) {
            try {
                $ip = Format::stripPortFromIp($ip);

                $reader = new Reader(self::$savePath['mmdbFile']);
                $countryData = $reader->get($ip);
                $reader->close();
                if (isset($countryData['country']['names'][LANG])) {
                    return $countryData['country']['names'][LANG];
                }

                return $countryData['country']['names']['en'];
            } catch (InvalidDatabaseException $e) {
                return false;
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param bool $useProxy
     * @param null|string $proxyServer
     * @param string $proxyUser
     * @param string $proxyPassword
     *
     * @return Client
     */
    public function getDownloadClient($useProxy = false, $proxyServer = null, $proxyUser = '', $proxyPassword = ''): Client
    {
        $clientOptions = [
            'timeout' => 2.0,
            'headers' => [
                'User-Agent' => 'MailWatch/' . mailwatch_version(),
            ],
        ];

        if (false !== $useProxy) {
            $proxyAuthString = '';
            if (!empty($proxyUser)) {
                $proxyAuthString = $proxyUser . ':' . $proxyPassword . '@';
            }
            $clientOptions['proxy'] = 'tcp://' . $proxyAuthString . $proxyServer;
        }

        return new Client($clientOptions);
    }

    /**
     * @param null $extractedFolder
     *
     * @return bool
     */
    public function cleanupFiles($extractedFolder = null): bool
    {
        @unlink(realpath(self::$savePath['md5']));
        @unlink(realpath(self::$savePath['database']));
        @unlink(realpath(substr(self::$savePath['database'], 0, -3)));

        if (null !== $extractedFolder) {
            array_map('unlink', glob(self::$savePath['extractTo'] . $extractedFolder . '/*'));
            rmdir(self::$savePath['extractTo'] . $extractedFolder);
        }

        return true;
    }

    /**
     * @param Client $client
     *
     * @return bool
     */
    public function downloadFiles(Client $client): bool
    {
        foreach ($this->download as $file => $url) {
            $response = $client->request('GET', $url, ['sink' => self::$savePath[$file]]);
            if (200 !== $response->getStatusCode()) {
                // download failed
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $filePath
     * @param string $md5Path
     *
     * @return bool
     */
    public function verifySignature($filePath, $md5Path): bool
    {
        return file_get_contents($md5Path) === md5_file($filePath);
    }

    public function decompressArchive()
    {
        $p = new \PharData(self::$savePath['database']);
        $p->decompress();
        $phar = new \PharData(substr(self::$savePath['database'], 0, -3));
        $phar->extractTo(self::$savePath['extractTo'], null, true);
    }

    /**
     * @return false|string
     */
    public function moveDatabaseFile()
    {
        foreach (new \DirectoryIterator(self::$savePath['extractTo']) as $file) {
            if ($file->isDot()) {
                continue;
            }
            if ($file->isDir()) {
                $extractedFolder = $file->getFilename();
                $archiveFilePath = realpath(self::$savePath['extractTo'] . $extractedFolder . '/GeoLite2-Country.mmdb');

                if (rename($archiveFilePath, self::$savePath['mmdbFile'])) {
                    return $extractedFolder;
                }
            }
        }

        return false;
    }
}
