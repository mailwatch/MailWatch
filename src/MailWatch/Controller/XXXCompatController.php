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

namespace MailWatch\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class XXXCompatController extends Controller
{

    /**
     * @Route("/status", name="status")
     * @Route("/", name="start")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_A') or is_granted('ROLE_U') or is_granted('ROLE_U')")
     */
    public function compatStatusCall()
    {
        return $this->compatCall("status.php");
    }

    /**
     * @Route("/{path}")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_A') or is_granted('ROLE_U') or is_granted('ROLE_U')")
     */
    public function compatCall($path)
    {
        $compatSrc = $this->get('kernel')->getProjectDir().'/mailscanner/';

        if (!is_readable($compatSrc . 'conf.php')) {
            die(__('cannot_read_conf'));
        }
        require_once $compatSrc . 'conf.php';
        require_once $compatSrc . 'functions.php';
        $usr=$this->getUser();
        $this->setSessionParams($usr);
        return $this->render($compatSrc . $path);
    }

    private function setSessionParams($usr)
    {
        $myusername=$usr->getUsername();
        $usertype=$usr->getType();

        $sql_userfilter = "SELECT filter FROM user_filters WHERE username='$myusername' AND active='Y'";
        $result_userfilter = \MailWatch\Db::query($sql_userfilter);

        $filter[] = $myusername;
        while ($row = $result_userfilter->fetch_array()) {
            $filter[] = $row['filter'];
        }
        $global_filter = address_filter_sql($filter, $usertype);

        switch ($usertype) {
            case 'A':
                $global_list = '1=1';
                break;
            case 'D':
                if (strpos($myusername, '@')) {
                    $ar = explode('@', $myusername);
                    $domainname = $ar[1];
                    if (defined('FILTER_TO_ONLY') && FILTER_TO_ONLY) {
                        $global_filter .= " OR to_domain='$domainname'";
                    } else {
                        $global_filter .= " OR to_domain='$domainname' OR from_domain='$domainname'";
                    }
                    $global_list = "to_domain='$domainname'";
                } else {
                    $global_list = "to_address='$myusername'";
                    foreach ($filter as $to_address) {
                        $global_list .= " OR to_address='$to_address'";
                    }
                }
                break;
            case 'U':
                $global_list = "to_address='$myusername'";
                foreach ($filter as $to_address) {
                    $global_list .= " OR to_address='$to_address'";
                }
                break;
        }

        $_SESSION['myusername'] = $usr->getUsername();
        $_SESSION['fullname'] = $usr->getFullname();
        $_SESSION['user_type'] = $usr->getType();
        $_SESSION['domain'] = (isset($domainname) ? $domainname : '');
        $_SESSION['global_filter'] = '(' . $global_filter . ')';
        $_SESSION['global_list'] = (isset($global_list) ? $global_list : '');
        $_SESSION['global_array'] = $filter;
        if (!isset($_SESSION['token'])) {
            $_SESSION['token'] = \MailWatch\Security::generateToken();
        }
        if (!isset($_SESSION['formtoken'])) {
            $_SESSION['formtoken'] = \MailWatch\Security::generateToken();
        }
    }
}
