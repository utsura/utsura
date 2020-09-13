<?php

/*
 * Copyright(c) 2019 SYSTEM FRIEND INC.
 */

namespace Plugin\CheckProduct4;

use Eccube\Common\EccubeConfig;
use Eccube\Event\TemplateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CheckProductEvent implements EventSubscriberInterface
{
	/**
	 * @var $eccubeConfig
	 */
	private $eccubeConfig;

	/**
	 * @var $session
	 */
	private $session;

	/**
	 * ProductReview constructor.
	 *
	 * @param SessionInterface $session
	 * @param EccubeConfig $eccubeConfig
	 *
	 */
	public function __construct(SessionInterface $session, EccubeConfig $eccubeConfig) {
		$this->session = $session;
		$this->eccubeConfig = $eccubeConfig;
	}

	/**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Product/detail.twig' => 'productDetail',
        ];
    }

    /**
     * @param TemplateEvent $event
     */
    public function productDetail(TemplateEvent $event)
    {
	    /** @var Product $Product */
	    $Product = $event->getParameter('Product');
	    $id = $Product->getId();

	    $arr = $this->session->get('plugin.check_product.product');
	    $arr[] = $id;
	    $arr = array_unique($arr);
	    $max = $this->eccubeConfig['PLG_CHECK_PRODUCT_MAX'];
	    $arr = array_slice($arr, (- $max), $max);

	    $this->session->set('plugin.check_product.product', $arr);
    }
}
