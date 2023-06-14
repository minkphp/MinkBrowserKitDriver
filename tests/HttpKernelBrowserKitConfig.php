<?php

namespace Behat\Mink\Tests\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface;
use Behat\Mink\Tests\Driver\Util\FixturesKernel;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

class HttpKernelBrowserKitConfig extends AbstractBrowserKitConfig
{
    public function createDriver(): DriverInterface
    {
        $client = new HttpKernelBrowser(new FixturesKernel());

        return new BrowserKitDriver($client);
    }

    public function getWebFixturesUrl(): string
    {
        return 'http://localhost';
    }
}
