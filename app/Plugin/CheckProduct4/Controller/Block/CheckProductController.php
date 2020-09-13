<?php

/*
 * Copyright(c) 2019 SYSTEM FRIEND INC.
 */

namespace Plugin\CheckProduct4\Controller\Block;

use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Repository\ProductRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class CheckProductController extends AbstractController
{
	/**
	 * @var RequestStack
	 */
	protected $session;

	/**
	 * @var ProductRepository
	 */
	protected $productRepository;

	public function __construct(SessionInterface $session,ProductRepository $productRepository) {
		$this->session = $session;
		$this->productRepository = $productRepository;
	}

	/**
	 * @Route("/block/check_product", name="block_check_product")
	 * @Template("Block/check_product.twig")
	 *
	 * @param Request $request
	 * @return array
	 */
	public function index(Request $request) {
		$productId =  $this->session->get('plugin.check_product.product') ?: array();
		$CheckProducts = array();
		foreach ($productId as $id) {
			$Product = $this->productRepository->find($id);
			if(!is_null($Product) && $Product->getStatus()->getId() === ProductStatus::DISPLAY_SHOW) {
				$CheckProducts[] = $Product;
			}
		}
		return [
			'CheckProducts' => $CheckProducts,
		];
	}
}
