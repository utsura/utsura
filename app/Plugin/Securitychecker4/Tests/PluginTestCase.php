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

namespace Plugin\Securitychecker3\Tests;

use Eccube\Application;
use Silex\WebTestCase;

abstract class PluginTestCase extends WebTestCase
{
    /**
     * {@inheritdoc}
     */
    public function createApplication()
    {
        $app = null;
        if (method_exists('\Eccube\Application', 'getInstance')) {
            $app = Application::getInstance();
        } else {
            $app = new Application();
        }

        $app['debug'] = true;

        $app->initialize();
        $app->initializePlugin();
        $app['session.test'] = true;
        $app['exception_handler']->disable();

        if (class_exists('\Eccube\Tests\Mock\CsrfTokenMock')) {
            $app['form.csrf_provider'] = $app->share(function () {
                return new \Eccube\Tests\Mock\CsrfTokenMock();
            });
        }

        $app->boot();

        return $app;
    }
}
