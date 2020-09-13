<?php

/*
 * Copyright(c) 2019 SYSTEM FRIEND INC.
 */

namespace Plugin\CheckProduct4;

use Eccube\Entity\Block;
use Eccube\Entity\BlockPosition;
use Eccube\Entity\Master\DeviceType;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\BlockRepository;
use Eccube\Repository\Master\DeviceTypeRepository;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class PluginManager extends AbstractPluginManager
{

	/**
	 * @var string コピー元ブロックファイル
	 */
	private $originBlock;

	/**
	 * @var string 最近チェックした商品
	 */
	private $blockName = '最近チェックした商品';

	/**check_product
	 * @var string ブロックファイル名
	 */
	private $blockFileName = 'check_product';

	/**
	 * PluginManager constructor.
	 */
	public function __construct()
	{
		// コピー元ブロックファイル
		$this->originBlock = __DIR__.'/Resource/template/Block/'.$this->blockFileName.'.twig';
	}

	/**
	 * @param null|array $meta
	 * @param ContainerInterface $container
	 *
	 * @throws Exception
	 */
	public function enable(array $meta, ContainerInterface $container)
	{
		$this->copyBlock($container);
		$Block = $container->get(BlockRepository::class)->findOneBy(['file_name' => $this->blockFileName]);
		if (is_null($Block)) {
			// pagelayoutの作成
			$this->createDataBlock($container);
		}
	}

	/**
	 * @param array|null $meta
	 * @param ContainerInterface $container
	 */
	public function update(array $meta, ContainerInterface $container)
	{
		$this->copyBlock($container);
	}

	/**
	 * @param array|null $meta
	 * @param ContainerInterface $container
	 * @throws Exception
	 */
	public function disable(array $meta, ContainerInterface $container)
	{
		$this->removeDataBlock($container);
	}

	/**
	 * @param array $meta
	 * @param ContainerInterface $container
	 * @throws Exception
	 */
	public function uninstall(array $meta, ContainerInterface $container)
	{
		// ブロックの削除
		$this->removeDataBlock($container);
		$this->removeBlock($container);
	}

	/**
	 * @param ContainerInterface $container
	 * @throws Exception
	 */
	protected function createDataBlock(ContainerInterface $container)
    {
	    $em = $container->get('doctrine.orm.entity_manager');
	    $DeviceType = $container->get(DeviceTypeRepository::class)->find(DeviceType::DEVICE_TYPE_PC);
	    try {
		    /** @var Block $Block */
		    $Block = $container->get(BlockRepository::class)->newBlock($DeviceType);
		    $Block->setFileName($this->blockFileName)
			    ->setName($this->blockName)
			    ->setUseController(true)
			    ->setDeletable(false);
		    $em->persist($Block);
		    $em->flush();
	    } catch (Exception $e) {
		    throw $e;
	    }
    }


	/**
	 * ブロックを削除.
	 *
	 * @param ContainerInterface $container
	 *
	 * @throws Exception
	 */
	private function removeDataBlock(ContainerInterface $container)
	{
		// Blockの取得(file_nameはアプリケーションの仕組み上必ずユニーク)
		/** @var Block $Block */
		$Block = $container->get(BlockRepository::class)->findOneBy(['file_name' => $this->blockFileName]);

		if (!$Block) {
			return;
		}

		$em = $container->get('doctrine.orm.entity_manager');
		try {
			// BlockPositionの削除
			$blockPositions = $Block->getBlockPositions();
			/** @var BlockPosition $BlockPosition */
			foreach ($blockPositions as $BlockPosition) {
				$Block->removeBlockPosition($BlockPosition);
				$em->remove($BlockPosition);
			}

			// Blockの削除
			$em->remove($Block);
			$em->flush();
		} catch (Exception $e) {
			throw $e;
		}
	}


	/**
	 * Copy block template.
	 *
	 * @param ContainerInterface $container
	 */
	private function copyBlock(ContainerInterface $container)
	{
		$templateDir = $container->getParameter('eccube_theme_front_dir');
		// ファイルコピー
		$file = new Filesystem();

		if (!$file->exists($templateDir.'/Block/'.$this->blockFileName.'.twig')) {
			// ブロックファイルをコピー
			$file->copy($this->originBlock, $templateDir.'/Block/'.$this->blockFileName.'.twig');
		}
	}

	/**
	 * Remove block template.
	 *
	 * @param ContainerInterface $container
	 */
	private function removeBlock(ContainerInterface $container)
	{
		$templateDir = $container->getParameter('eccube_theme_front_dir');
		$file = new Filesystem();
		$file->remove($templateDir.'/Block/'.$this->blockFileName.'.twig');
	}
}
