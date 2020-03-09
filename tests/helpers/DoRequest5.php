<?php


namespace Behat\Mink\Tests\Driver;

require_once __DIR__ . '/AbstractTestClient.php';

abstract class DoRequestReturnObject extends AbstractTestClient
{
    protected function doRequest($request)
    {
        return $this->_doRequest($request);
    }
}
