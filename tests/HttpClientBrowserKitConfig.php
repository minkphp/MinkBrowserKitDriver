<?php

namespace Behat\Mink\Tests\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use Symfony\Component\BrowserKit\HttpBrowser;

class HttpClientBrowserKitConfig extends AbstractBrowserKitConfig
{
    public function createDriver()
    {
        return new BrowserKitDriver(new HttpBrowser());
    }
}
