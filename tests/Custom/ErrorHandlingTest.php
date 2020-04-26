<?php

namespace Behat\Mink\Tests\Driver\Custom;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Exception\DriverException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Exception\BadMethodCallException;
use PHPUnit\Framework\Constraint\Exception as ExceptionConstraint;
use PHPUnit\Framework\Constraint\ExceptionMessage;

class ErrorHandlingTest extends TestCase
{
    /**
     * @var TestClient
     */
    private $client;

    protected function setUp()
    {
        $this->client = new TestClient();
    }

    public function testGetClient()
    {
        $this->assertSame($this->client, $this->getDriver()->getClient());
    }

    /**
     * @expectedException \Behat\Mink\Exception\DriverException
     * @expectedExceptionMessage Unable to access the response before visiting a page
     *
     * Looks like we have to mark these tests as "legacy", otherwise we get deprecation errors.
     * Although the deprecations are handled, there's no way to avoid the deprecation message here.
     * @group legacy
     */
    public function testGetResponseHeaderWithoutVisit()
    {
        $this->getDriver()->getResponseHeaders();
    }

    /**
     * Looks like we have to mark these tests as "legacy", otherwise we get deprecation errors.
     * Although the deprecations are handled, there's no way to avoid the deprecation message here.
     * @group legacy
     */
    public function testFindWithoutVisit()
    {
        $exception = null;
        try {
            $this->getDriver()->find('//html');
        } catch (\Exception $exception) {
        }

        if ($exception instanceof BadMethodCallException) {
            $expectedMessage = sprintf(
                'The "request()" method must be called before "%s::getCrawler()".',
                'Symfony\Component\BrowserKit\AbstractBrowser'
            );
            $this->assertException(
                $exception,
                'Symfony\Component\BrowserKit\Exception\BadMethodCallException'
            );
            $this->assertExceptionMessage($exception, $expectedMessage);
            return;
        }
        $this->assertException($exception,'Behat\Mink\Exception\DriverException');
        $this->assertExceptionMessage(
            $exception,
            'Unable to access the response content before visiting a page'
        );
    }

    /**
     * @expectedException \Behat\Mink\Exception\DriverException
     * @expectedExceptionMessage Unable to access the request before visiting a page
     *
     * Looks like we have to mark these tests as "legacy", otherwise we get deprecation errors.
     * Although the deprecations are handled, there's no way to avoid the deprecation message here.
     * @group legacy
     */
    public function testGetCurrentUrlWithoutVisit()
    {
        $this->getDriver()->getCurrentUrl();
    }

    /**
     * @expectedException \Behat\Mink\Exception\DriverException
     * @expectedExceptionMessage The selected node has an invalid form attribute (foo)
     */
    public function testNotMatchingHtml5FormId()
    {
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

    /**
     * @expectedException \Behat\Mink\Exception\DriverException
     * @expectedExceptionMessage The selected node has an invalid form attribute (foo)
     */
    public function testInvalidHtml5FormId()
    {
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

    /**
     * @expectedException \Behat\Mink\Exception\DriverException
     * @expectedExceptionMessage The selected node does not have a form ancestor.
     */
    public function testManipulateInputWithoutForm()
    {
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

    /**
     * @expectedException \Behat\Mink\Exception\DriverException
     * @expectedExceptionMessage Behat\Mink\Driver\BrowserKitDriver supports clicking on links and submit or reset buttons only. But "div" provided
     */
    public function testClickOnUnsupportedElement()
    {
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

    /**
     * @param null|\Throwable $exception
     * @param string          $expectedExceptionClass
     */
    private function assertException($exception, $expectedExceptionClass)
    {
        if (class_exists('\PHPUnit_Framework_Constraint_Exception')) {
            $constraint = new \PHPUnit_Framework_Constraint_Exception($expectedExceptionClass);
        } else {
            $constraint = new ExceptionConstraint($expectedExceptionClass);
        }
        $this->assertThat($exception, $constraint);
    }

    /**
     * @param null|\Throwable $exception
     * @param string          $expectedMessage
     */
    private function assertExceptionMessage($exception, $expectedMessage)
    {
        if (class_exists('\PHPUnit_Framework_Constraint_ExceptionMessage')) {
            $constraint = new \PHPUnit_Framework_Constraint_ExceptionMessage($expectedMessage);
        } else {
            $constraint = new ExceptionMessage($expectedMessage);
        }
        $this->assertThat($exception, $constraint);
    }
}

if (class_exists('\Symfony\Component\BrowserKit\AbstractBrowser')) {

    class TestClient extends AbstractBrowser {

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

        protected function doRequest($request)
        {
            if (null === $this->nextResponse) {
                return new Response();
            }

            $response = $this->nextResponse;
            $this->nextResponse = null;

            return $response;
        }
    }

} else {

    class TestClient extends Client {

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

        protected function doRequest($request)
        {
            if (null === $this->nextResponse) {
                return new Response();
            }

            $response = $this->nextResponse;
            $this->nextResponse = null;

            return $response;
        }
    }
}
