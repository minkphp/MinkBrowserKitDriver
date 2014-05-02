<?php

namespace Tests\Behat\Mink\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Session;
use Symfony\Component\HttpKernel\Client;

/**
 * @group functional
 */
class BrowserKitDriverTest extends GeneralDriverTest
{
    protected static function getDriver()
    {
        $client = new Client(require(__DIR__.'/../../../app.php'));
        $driver = new BrowserKitDriver($client);

        return $driver;
    }

    protected function pathTo($path)
    {
        return 'http://localhost'.$path;
    }

    public function testBaseUrl()
    {
        $client = new Client(require(__DIR__.'/../../../app.php'));
        $driver = new BrowserKitDriver($client, 'http://localhost/foo/');
        $session = new Session($driver);

        $session->visit('http://localhost/foo/index.php');
        $this->assertEquals(200, $session->getStatusCode());
        $this->assertEquals('http://localhost/foo/index.php', $session->getCurrentUrl());
    }
}
