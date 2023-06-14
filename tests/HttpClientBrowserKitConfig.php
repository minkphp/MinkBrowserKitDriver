<?php

namespace Behat\Mink\Tests\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\DriverInterface;
use Symfony\Component\BrowserKit\HttpBrowser;

class HttpClientBrowserKitConfig extends AbstractBrowserKitConfig
{
    public function createDriver(): DriverInterface
    {
        return new BrowserKitDriver(new HttpBrowser());
    }
}
