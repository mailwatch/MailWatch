<?php
namespace MailWatch\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

class LuckyController
{

    /**
     * @Route("/lucky/number")
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_A')")
     */
    public function number()
    {
        $number = mt_rand(0, 100);

        return new Response(
            '<html><body>Lucky number: '.$number.'</body></html>'
        );
    }
}
