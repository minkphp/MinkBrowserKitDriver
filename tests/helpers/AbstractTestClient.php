<?php

namespace Behat\Mink\Tests\Driver;

use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpKernel\Kernel;

if (Kernel::VERSION_ID >= 40300) {
    require_once __DIR__ . '/AbstractClientForAbstractBrowser.php';
} else {
    require_once __DIR__ . '/AbstractClientForClient.php';
}

abstract class AbstractTestClient extends AbstractClient
{
    protected $nextResponse = null;
    protected $nextScript = null;
    public function setNextResponse(Response $response)
    {
        $this->nextResponse = $response;
    }

    public function setNextScript($script)
    {
        $this->nextScript = $script;
    }
    protected function _doRequest($request)
    {
        if (null === $this->nextResponse) {
            return new Response();
        }

        $response = $this->nextResponse;
        $this->nextResponse = null;

        return $response;
    }
}
