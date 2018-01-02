<?php
namespace MailWatch\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends Controller
{
    /**
     * @Route("/login", name="login")
     */
    public function login(Request $request, AuthenticationUtils $authUtils)
    {
        // get the login error if there is one
        $error = $authUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authUtils->getLastUsername();

        //required for localization
        $compatSrc = $this->get('kernel')->getProjectDir().'/mailscanner/';
        if (!is_readable($compatSrc . 'conf.php')) {
            die(__('cannot_read_conf'));
        }
        require_once $compatSrc . 'conf.php';
        require_once $compatSrc . 'functions.php';



        return $this->render('Security/login.html.php', array(
            'last_username' => $lastUsername,
            'error'         => $error,
        ));
    }
}
