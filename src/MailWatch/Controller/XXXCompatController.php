<?php
namespace MailWatch\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class XXXCompatController extends Controller
{

    /**
     * @Route("/status", name="status")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_A')")
     */
    public function compatStatusCall()
    {
        return $this->compatCall("status.php");
    }

    /**
     * @Route("/{path}")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_A')")
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

    private function setSessionParams($usr){
        $myusername=$usr->getUsername();
        $usertype=$usr->getType();

        $sql_userfilter = "SELECT filter FROM user_filters WHERE username='$myusername' AND active='Y'";
        $result_userfilter = dbquery($sql_userfilter);

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
        $_SESSION['token'] = generateToken();
        $_SESSION['formtoken'] = generateToken();

    }
}
