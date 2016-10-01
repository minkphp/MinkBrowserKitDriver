<?php

namespace Behat\Mink\Tests\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Tests\Driver\Util\FixturesKernel;
use Symfony\Component\HttpKernel\Client;

class BrowserKitConfig extends AbstractConfig
{
    public static function getInstance()
    {
        return new self();
    }

    /**
     * {@inheritdoc}
     */
    public function createDriver()
    {
        $client = new Client(new FixturesKernel());

        return new BrowserKitDriver($client);
    }

    /**
     * {@inheritdoc}
     */
    public function getWebFixturesUrl()
    {
        return 'http://localhost';
    }

    protected function supportsJs()
    {
        return false;
    }
}
