<?php

namespace Behat\Mink\Tests\Driver;

abstract class AbstractBrowserKitConfig extends AbstractConfig
{
    public static function getInstance()
    {
        return new static();
    }

    protected function supportsJs()
    {
        return false;
    }
}
