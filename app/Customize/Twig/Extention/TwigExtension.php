<?php
    namespace Customize\Twig\Extension;
     
    use Doctrine\Common\Collections;
    use Doctrine\ORM\EntityManagerInterface;
    use Eccube\Common\EccubeConfig;
    use Eccube\Entity\Master\ProductStatus;
    use Eccube\Entity\Product;
    use Eccube\Entity\ProductClass;
    use Eccube\Repository\ProductRepository;
     
    class TwigExtension extends \Twig_Extension
    {
        private $entityManager;
        protected $eccubeConfig;
        private $productRepository;
     
        /**
            TwigExtension constructor.
        **/
        public function __construct(
            EntityManagerInterface $entityManager,
            EccubeConfig $eccubeConfig, 
            ProductRepository $productRepository
        ) {
            $this->entityManager = $entityManager;
            $this->eccubeConfig = $eccubeConfig;
            $this->productRepository = $productRepository;
        }
        /**
            Returns a list of functions to add to the existing list.
            @return array An array of functions
        **/
        public function getFunctions()
        {
            return array(
                new \Twig_SimpleFunction('CustomizeNewProduct', array($this, 'getCustomizeNewProduct')),
            );
        }
     
        /**
            Name of this extension
            @return string
        **/
        public function getName()
        {
            return 'CustomizeTwigExtension';
        }
     
        /**
            新着商品を10件返す
            @return Products|null
        **/
        public function getCustomizeNewProduct()
        {
            try {
                $searchData = array();
                $qb = $this->entityManager->createQueryBuilder();
                $query = $qb->select("plob")
                    ->from("Eccube\\Entity\\Master\\ProductListOrderBy", "plob")
                    ->where('plob.id = :id')
                    ->setParameter('id', $this->eccubeConfig['eccube_product_order_newer'])
                    ->getQuery();
                $searchData['orderby'] = $query->getOneOrNullResult();
     
                // 新着順の商品情報10件取得
                $qb = $this->productRepository->getQueryBuilderBySearchData($searchData);
                $query = $qb->setMaxResults(10)->getQuery();
                $products = $query->getResult();
                return $products;
     
            } catch (\Exception $e) {    /* トップページ新着商品リスト */
                .ec-newItemRole .ec-newItemRole__list{
                    flex-wrap:wrap;
                }
                .ec-newItemRole .ec-newItemRole__list .ec-newItemRole__listItem{
                    width:20%;
                    margin:0 auto 20px auto;
                }
                .ec-newItemRole .ec-newItemRole__list .ec-newItemRole__listItem a{
                    max-width:96%;
                    display:block;
                }
                .ec-newItemRole .ec-newItemRole__listItemPrice{
                    color:#cc0000;
                    font-weight:bold;
                    text-align: right;
                    font-size:18px;
                }
                @media screen and (max-width:768px){
                    
                    .ec-newItemRole .ec-newItemRole__list .ec-newItemRole__listItem{
                        width:33.3%;
                    }
                    
                }
                return null;
            }
            return null;
        }
    }
    ?>