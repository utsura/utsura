<?php

namespace Plugin\CategoryExtensionB\Form\Extension;

use Eccube\Common\EccubeConfig;
use Eccube\Form\Type\Admin\CategoryType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class CategoryTypeExtension extends AbstractTypeExtension
{
    /**
     * @var EccubeConfig
     */
    private $eccubeConfig;

    public function __construct(EccubeConfig $eccubeConfig )
    {
        $this->eccubeConfig = $eccubeConfig;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('header_contents', TextareaType::class, [
                'label' => trans('category_extension_b.admin.header_contents.title'),
                'required' => false,
                'attr' => [
                    'maxlength' => $this->eccubeConfig['category_extension_b.text_area_len'],
                    'placeholder' => trans('category_extension_b.admin.header_contents.placeholder'),
                ],
                'eccube_form_options' => [
                    'auto_render' => true,
                ],
                'constraints' => [
                    new Assert\Length(['max' => $this->eccubeConfig['category_extension_b.text_area_len']]),
                ],
            ])->add('footer_contents', TextareaType::class, [
                'label' => trans('category_extension_b.admin.footer_contents.title'),
                'required' => false,
                'attr' => [
                    'maxlength' => $this->eccubeConfig['category_extension_b.text_area_len'],
                    'placeholder' => trans('category_extension_b.admin.footer_contents.placeholder'),
                ],
                'eccube_form_options' => [
                    'auto_render' => true,
                ],
                'constraints' => [
                    new Assert\Length(['max' => $this->eccubeConfig['category_extension_b.text_area_len']]),
                ],
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return CategoryType::class;
    }
}
