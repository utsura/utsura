<?php

namespace Plugin\CategoryExtensionB\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation as Eccube;

/**
 * @Eccube\EntityExtension("Eccube\Entity\Category")
 */
trait CategoryTrait
{
    /**
     * @var string
     *
     * @ORM\Column(name="header_contents", type="string", length=4000, nullable=true)
     */
    private $header_contents;

    /**
     * @var string
     *
     * @ORM\Column(name="footer_contents", type="string", length=4000, nullable=true)
     */
    private $footer_contents;

    /**
     * @return string
     */
    public function getHeaderContents()
    {
        return $this->header_contents;
    }

    /**
     * @param string $header_contents
     */
    public function setHeaderContents($header_contents)
    {
        $this->header_contents = $header_contents;
    }

    /**
     * @return string
     */
    public function getFooterContents()
    {
        return $this->footer_contents;
    }

    /**
     * @param string $footer_contents
     */
    public function setFooterContents($footer_contents)
    {
        $this->footer_contents = $footer_contents;
    }
}
