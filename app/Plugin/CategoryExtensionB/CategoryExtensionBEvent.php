<?php

namespace Plugin\CategoryExtensionB;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Eccube\Event\TemplateEvent;

class CategoryExtensionBEvent implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            '@admin/Product/category.twig' => ['onTemplateAdminProductCategory', 10],
            'Product/list.twig' => ['onTemplateProductList', 10],
        ];
    }

    /**
     * @param TemplateEvent $templateEvent
     */
    public function onTemplateProductList(TemplateEvent $templateEvent)
    {
        $templateEvent->addSnippet('@CategoryExtensionB/default/category.twig');
    }

    /**
     * @param TemplateEvent $templateEvent
     */
    public function onTemplateAdminProductCategory(TemplateEvent $templateEvent)
    {
        $templateEvent->addSnippet('@CategoryExtensionB/admin/category.twig');
    }
}
