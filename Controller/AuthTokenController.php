<?php
namespace EMS\CoreBundle\Controller;

use EMS\CoreBundle\Entity\AuthToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthTokenController extends AppController
{
    /**
     * @Route("/auth-token", name="auth-token", defaults={"_format": "json"}, methods={"POST"})
     */
    public function postAuthTokensAction(Request $request) : Response
    {
        $loginInfo = json_decode($request->getContent(), true);
        

        $userService = $this->getUserService();
        $factory = $this->getSecurityEncoder();
        
        $user = $userService->getUser($loginInfo['username'], false);
        
        if (empty($user)) { //le user n'est pas trouvés
            return $this->invalidCredentials();
        }
        
        $encoder = $factory->getEncoder($user);
        
        if ($encoder->isPasswordValid($user->getPassword(), $loginInfo['password'], $user->getSalt())) {
            $authToken = new AuthToken($user);
            
            $em = $this->getDoctrine()->getManager();

            $em->persist($authToken);
            $em->flush();

            return $this->render('@EMSCore/ajax/auth-token.json.twig', [
                    'authToken' => $authToken,
                    'success' => true,
            ]);
        } else { // Le mot de passe n'est pas correct
            return $this->invalidCredentials();
        }
    }

    private function invalidCredentials()
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(json_encode([
            'success' => false,
            'acknowledged' => true,
            'error' => ['Unauthorized Error'],
        ]))->setStatusCode(401);
        return $response;
    }
}
