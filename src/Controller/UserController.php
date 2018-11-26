<?php
/**
 * Created by PhpStorm.
 * User: Etudiant
 * Date: 21/11/2018
 * Time: 13:03
 */

namespace App\Controller;


use App\Entity\User;
use App\Form\LoginType;
use App\User\UserFactory;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;


class UserController extends Controller
{

    /**
     * @Route("/register", name="user_register", methods={"GET", "POST"})
     * @param Request $request
     * @param EntityManagerInterface $em
     * @param UserPasswordEncoderInterface $encoder
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function register(Request $request, EntityManagerInterface $em, UserPasswordEncoderInterface $encoder)
    {


        $form = $this   ->createForm(LoginType::class )
                        ->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {

            # Getting the data:
            $task = $form->getData();
            $email = $task['email'];
            $password = $task['password'];

            $user = new User($encoder);
            $user->setEmail($email);
            $user->setPassword($password);

            try{
                $em->persist($user);
                $em->flush();
            }
            catch(\Exception $e)
            {
                echo 'oops. $e';
            }

            return $this->redirectToRoute('security_login');
        }


        return $this->render('user/registration.html.twig',[
            'form' => $form->createView()
        ]);
    }
}