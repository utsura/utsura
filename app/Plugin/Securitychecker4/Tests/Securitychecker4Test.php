<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Securitychecker4\Tests;

use Eccube\Common\Constant;
use Plugin\Securitychecker3\Controller\ConfigController;
use Plugin\Securitychecker3\Repository\ConfigRepository;
use Plugin\Securitychecker3\Service\Securitychecker3Service;

class Securitychecker4Test extends PluginTestCase
{
    /** @var ConfigController */
    protected $ConfigController;

    /** @var ConfigRepository */
    protected $ConfigRepository;

    /** @var Securitychecker3Service */
    protected $Securitychecker3Service;

    public function setUp()
    {
        parent::setUp();
        if (isset($this->app['orm.em'])) {
            $this->app['orm.em']->getConnection()->beginTransaction();
        }
        $this->ConfigController = new ConfigController();
        $this->ConfigRepository = $this->app['orm.em']->getRepository('\Plugin\Securitychecker3\Entity\Config');
        $this->Securitychecker3Service = $this->app['eccube.service.securitychecker3'];
    }

    public function testGetInstance()
    {
        $this->assertInstanceOf('\Plugin\Securitychecker3\Repository\ConfigRepository', $this->ConfigRepository);
        $this->assertInstanceOf('\Plugin\Securitychecker3\Service\Securitychecker3Service', $this->Securitychecker3Service);
    }

    public function testSaveCheckResult()
    {
        if (version_compare('3.0.9', Constant::VERSION, '>')) {
            $this->markTestSkipped('Has been supported in EC-CUBE since version 3.0.9');
        }
        $CheckResult = json_encode(['eccube_version' => Constant::VERSION]);
        $this->ConfigRepository->saveCheckResult($CheckResult);
    }

    public function testGetCheckResult()
    {
        $expected = ['eccube_version' => Constant::VERSION];
        $CheckResult = json_encode($expected);
        $this->ConfigRepository->saveCheckResult($CheckResult);

        $actual = $this->ConfigRepository->getCheckResult();

        $this->assertEquals($expected, $actual);
    }

    public function testGetSiteUrl()
    {
        $expected = 'http://localhost/';
        $actual = $this->Securitychecker3Service->getSiteUrl();

        $this->assertEquals($expected, $actual);
    }

    public function testCheckResources()
    {
        // 外部から閲覧可能な html/robots.txt を指定する
        $file = realpath($this->app['config']['root_dir'].'/html/robots.txt');

        $expected = '/robots.txt';
        $actual = $this->Securitychecker3Service->checkResources($file);

        $this->assertContains($expected, $actual);
    }

    public function testSearchResources()
    {
        // 外部から閲覧可能な html を指定する
        $path = 'html';
        $expected = ['/html/robots.txt'];
        $actual = $this->Securitychecker3Service->searchResources($path);

        $this->assertEquals($expected, $actual);
    }

    public function testSearchResourcesWithFile()
    {
        // 外部から閲覧可能な html を指定する
        $path = 'html/install.php';
        $expected = ['/html/install.php'];
        $actual = $this->Securitychecker3Service->searchResources($path);

        $this->assertEquals($expected, $actual);
    }

    public function testPluginConfigs()
    {
        $actual = $this->Securitychecker3Service->parsePluginConfigs();
        $this->assertNotEmpty($actual);
    }
}
