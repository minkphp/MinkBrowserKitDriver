<?php

namespace Tests\Behat\Mink\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use Symfony\Component\HttpKernel\Client;

/**
 * @group browserkitdriver
 */
class BrowserKitDriverTest extends HeadlessDriverTest
{
    protected static function getDriver()
    {
        $client = new Client(require(__DIR__.'/../../../app.php'));
        $driver = new BrowserKitDriver($client);
        $driver->setRemoveScriptFromUrl(false);

        return $driver;
    }

    protected function pathTo($path)
    {
        return 'http://localhost'.$path;
    }

    public function testCloneDriver()
    {
        $driver1 = $this->getDriver();
        $driver2 = clone $driver1;

        $this->assertNotSame($driver1->getClient(), $driver2->getClient());
    }
}
