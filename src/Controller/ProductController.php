<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\Slugify;

final class ProductController extends AbstractController
{
//    #[Route('/product', name: 'app_product')]
//    public function index(): Response
//    {
//        return $this->render('product/index.html.twig', [
//            'controller_name' => 'ProductController',
//        ]);
//    }

    #[Route('/products', name: 'app_product_list')]
    public function listProducts(): Response
    {
        // Render Twig avec le H1 "Liste des produits"
        return $this->render('product/list.html.twig', [
            'title' => 'Liste des produits',
        ]);
    }


    #[Route('/product/{id}', name: 'product_view')]
    public function viewProduct(int $id, Slugify $slugify): Response
    {
        $productTitle = "T-Shirt d'Été !";

        $slug = $slugify->slugify($productTitle);

        return $this->render('product/view.html.twig', [
            'productId' => $id,
            'productTitle' => $productTitle,
            'productSlug' => $slug,
        ]);
    }
}
