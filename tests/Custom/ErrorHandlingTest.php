<?php

namespace Behat\Mink\Tests\Driver\Custom;

use Behat\Mink\Driver\BrowserKitDriver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Response;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectException;

class ErrorHandlingTest extends TestCase
{
    use ExpectException;

    /**
     * @var TestClient
     */
    private $client;

    /**
     * @before
     */
    protected function prepareClient()
    {
        $this->client = new TestClient();
    }

    public function testGetClient()
    {
        $this->assertSame($this->client, $this->getDriver()->getClient());
    }

    /**
     * Looks like we have to mark these tests as "legacy", otherwise we get deprecation errors.
     * Although the deprecations are handled, there's no way to avoid the deprecation message here.
     * @group legacy
     */
    public function testGetResponseHeaderWithoutVisit()
    {
        $this->expectException('\Behat\Mink\Exception\DriverException');
        $this->expectExceptionMessage('Unable to access the response before visiting a page');
        $this->getDriver()->getResponseHeaders();
    }

    /**
     * Looks like we have to mark these tests as "legacy", otherwise we get deprecation errors.
     * Although the deprecations are handled, there's no way to avoid the deprecation message here.
     * @group legacy
     */
    public function testFindWithoutVisit()
    {
        $this->expectException('\Behat\Mink\Exception\DriverException');
        $this->expectExceptionMessage('Unable to access the response content before visiting a page');
        $this->getDriver()->find('//html');
    }

    /**
     * Looks like we have to mark these tests as "legacy", otherwise we get deprecation errors.
     * Although the deprecations are handled, there's no way to avoid the deprecation message here.
     * @group legacy
     */
    public function testGetCurrentUrlWithoutVisit()
    {
        $this->expectException('\Behat\Mink\Exception\DriverException');
        $this->expectExceptionMessage('Unable to access the request before visiting a page');
        $this->getDriver()->getCurrentUrl();
    }

    public function testNotMatchingHtml5FormId()
    {
        $this->expectException('\Behat\Mink\Exception\DriverException');
        $this->expectExceptionMessage('The selected node has an invalid form attribute (foo)');
        $html = <<<'HTML'
<html>
<body>
    <form id="test">
        <input name="test" value="foo" form="foo">
        <input type="submit">
    </form>
</body>
</html>
HTML;

        $this->client->setNextResponse(new Response($html));

        $driver = $this->getDriver();
        $driver->visit('/index.php');
        $driver->setValue('//input[./@name="test"]', 'bar');
    }

    public function testInvalidHtml5FormId()
    {
        $this->expectException('\Behat\Mink\Exception\DriverException');
        $this->expectExceptionMessage('The selected node has an invalid form attribute (foo)');
        $html = <<<'HTML'
<html>
<body>
    <form id="test">
        <input name="test" value="foo" form="foo">
        <input type="submit">
    </form>
    <div id="foo"></div>
</body>
</html>
HTML;

        $this->client->setNextResponse(new Response($html));

        $driver = $this->getDriver();
        $driver->visit('/index.php');
        $driver->setValue('//input[./@name="test"]', 'bar');
    }

    public function testManipulateInputWithoutForm()
    {
        $this->expectException('\Behat\Mink\Exception\DriverException');
        $this->expectExceptionMessage('The selected node does not have a form ancestor.');
        $html = <<<'HTML'
<html>
<body>
    <form id="test">
        <input type="submit">
    </form>
    <div id="foo">
        <input name="test" value="foo">
    </div>
</body>
</html>
HTML;

        $this->client->setNextResponse(new Response($html));

        $driver = $this->getDriver();
        $driver->visit('/index.php');
        $driver->setValue('//input[./@name="test"]', 'bar');
    }

    public function testClickOnUnsupportedElement()
    {
        $this->expectException('\Behat\Mink\Exception\DriverException');
        $this->expectExceptionMessage('Behat\Mink\Driver\BrowserKitDriver supports clicking on links and submit or reset buttons only. But "div" provided');
        $html = <<<'HTML'
<html>
<body>
    <div></div>
</body>
</html>
HTML;

        $this->client->setNextResponse(new Response($html));

        $driver = $this->getDriver();
        $driver->visit('/index.php');
        $driver->click('//div');
    }

    private function getDriver()
    {
        return new BrowserKitDriver($this->client);
    }
}

class TestClient extends AbstractBrowser
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

    protected function doRequest($request): object
    {
        if (null === $this->nextResponse) {
            return new Response();
        }

        $response = $this->nextResponse;
        $this->nextResponse = null;

        return $response;
    }
}
