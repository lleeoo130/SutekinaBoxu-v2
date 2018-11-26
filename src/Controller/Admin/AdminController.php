<?php
/**
 * Created by PhpStorm.
 * User: Etudiant
 * Date: 22/11/2018
 * Time: 10:59
 */

namespace App\Controller\Admin;

use App\Entity\Box;
use App\Form\BoxType;
use App\Provider\ProductsProvider;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\Exception\TransitionException;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Yaml\Yaml;


class AdminController extends AbstractController
{

    public $workflows;

    public function __construct(Registry $workflows)
    {
        $this->workflows = $workflows;
    }

    /**
     * @Route("/admin", name="administration_main")
     */
    public function admin()
    {
        
        return $this->render('admin/main.html.twig');
    }

    /**
     * @Route("/admin/current_box", name="administration_current_box")
     * @param ObjectManager $objectManager
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function currentBox(ObjectManager $objectManager)
    {
        if (isset($objectManager->getRepository(Box::class)->findActiveBox()[0])){
            $box =  $objectManager->getRepository(Box::class)->findActiveBox()[0];

            return $this->render('admin/current_box.html.twig', [
                'box' => $box
            ]);
        }
        return $this->render('admin/current_box.html.twig');
    }

    /**
     * @Route("/admin/current_box/choose_products", name="administration_current_box_choosing", methods={"GET", "POST"})
     * @param ObjectManager $em
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function chooseProductForBox(ObjectManager $em, Request $request)
    {
        $yamlConponent = new Yaml();
        $file = file_get_contents(__DIR__.'\..\..\Provider\kawaii_products.yaml');

        # Getting our products
        $products = new ProductsProvider();
        $products = $products->productArrayFromYaml($yamlConponent, $file);
        $products = $products['products'];

        $box = $em->getRepository(Box::class)->findActiveBox();

        # If there'snt an active box
        if (empty($box))
        {
            return $this->redirectToRoute('admin_new_box');
        }
        $box = $box[0];

        $data = $request->request;

        if (!empty($data)){

            foreach ($data as $selectedProducts => $value)
            {

                if ($value == 'delete')
                {
                    $productToRemove = $products[$selectedProducts];
                    $box->removeProduct($productToRemove);
                }
                elseif ($value == 'validate_order')
                {

                    # change status
                    $box->changeStatus('Ordered_from_catalogue');

                    $workflow = $this->workflows->get($box);

                    try {
                        $workflow->apply($box, 'order_passed');
                    } catch (TransitionException $exception) {
                        dump($exception);
                    }

                    $em->flush();
                    # redirect to next page

                    return $this->render('admin/current_box_order_has_arrived.html.twig', [
                        'box' => $box
                    ]);
                }
                elseif ($value !== 'delete')
                {
                    $productToAdd = $products[$selectedProducts];
                    # add to Box;
                    $box->addProduct($productToAdd);
                }
            }
            $em->flush();
        }

        return $this->render('admin/current_box_choose_products.html.twig', [
            'products'  => $products,
            'box'       => $box
        ]);
    }


    /**
     * @Route("/admin/create_new_box", name="admin_new_box", methods={"GET", "POST"})
     * @param Request $request
     * @param ObjectManager $manager
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function createNewBox(Request $request, ObjectManager $manager)
    {
        $form = $this->createForm(BoxType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $task = $form->getData();

            $manager->persist($task);
            $manager->flush();

            return $this->redirectToRoute('administration_current_box_choosing');
    }
        return $this->render('admin/create_new_box.html.twig', [
            'form' => $form->createView(),
            'creatingbox' => true
        ]);
    }

    /**
     * @Route("/admin/current_box/product_manager", name="administration_current_box_product_manager")
     * @param ObjectManager $objectManager
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function orderHasArrived(ObjectManager $objectManager, Request $request)
    {
        $box =  $objectManager->getRepository(Box::class)->findActiveBox();
        $box = $box[0];

       $workflows = $this->workflows->get($box);

       $status = $request->request->get('status');

       if (isset($status)){
           if ( $status == 'arrived' && $workflows->can($box, 'order_received'))
           {
                $workflows->apply($box, 'order_received');
                $objectManager->flush();
           }
           elseif ($status == 'validated' && $workflows->can($box, 'order_approved') )
           {
               $workflows->apply($box, 'order_approved');
               $objectManager->flush();
               return $this->render('admin/current_box_ready_to_go.html.twig', [
                   'box' => $box
               ]);
           }
           elseif ($status == 'refused' && $workflows->can($box, 'order_refused') )
           {
               $workflows->apply($box, 'order_refused');
               $objectManager->flush();
               return $this->redirectToRoute('administration_current_box_choosing');
           }
       }

        return $this->render('admin/current_box_order_has_arrived.html.twig', [
            'box' => $box
        ]);
    }

    /**
     * @Route("/admin/current_box/ready_to_go", name="administration_current_box_ready")
     * @param ObjectManager $objectManager
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function orderIsReadyToGo(ObjectManager $objectManager, Request $request)
    {
        $box =  $objectManager->getRepository(Box::class)->findActiveBox();
        $box = $box[0];

        $workflows = $this->workflows->get($box);

        $status = $request->request->get('status');

        if ( $status == 'ready')
        {
            $workflows->apply($box, 'ready_to_distribute');
            $objectManager->flush();

            $archivebox = true;

            return $this->render('admin/current_box_ready_to_go.html.twig', [
                'box' => $box,
                'archivebox' => $archivebox
            ]);
        }
        elseif ( $status == 'archive' ){

            $box->setActive(false);
            $objectManager->flush();
            $archivebox = true;

            $boxes = $objectManager->getRepository(Box::class)->findAllUnactive();

            return $this->render('admin/archive.html.twig', [
                'archivebox' => $archivebox,
                'boxes'      => $boxes
            ]);
        }
        return $this->render('admin/current_box_ready_to_go.html.twig', [
        'box' => $box
            ]);

    }

    /**
     * @Route("/admin/archive", name="administration_archive")
     */
    public function archive(ObjectManager $objectManager)
    {
        if (isset($objectManager->getRepository(Box::class)->findActiveBox()[0])){

            $box = $objectManager->getRepository(Box::class)->findActiveBox()[0];
            $box->setActive(false);
            $objectManager->flush();

        }

        $boxes = $objectManager->getRepository(Box::class)->findAllUnactive();


        return $this->render('admin/archive.html.twig', [
            'active' => 'previousbox',
            'boxes' => $boxes
        ]);
    }


}