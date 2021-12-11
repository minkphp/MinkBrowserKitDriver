<?php

namespace Behat\Mink\Tests\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Tests\Driver\Util\FixturesKernel;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

class HttpKernelBrowserKitConfig extends AbstractBrowserKitConfig
{
    /**
     * {@inheritdoc}
     */
    public function createDriver()
    {
        $client = new HttpKernelBrowser(new FixturesKernel());

        return new BrowserKitDriver($client);
    }

    /**
     * {@inheritdoc}
     */
    public function getWebFixturesUrl()
    {
        return 'http://localhost';
    }
}
