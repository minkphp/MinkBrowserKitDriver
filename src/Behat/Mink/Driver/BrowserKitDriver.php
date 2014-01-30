<?php

namespace Behat\Mink\Driver;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Session;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpKernel\Client as HttpKernelClient;

/*
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Symfony2 BrowserKit driver.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class BrowserKitDriver extends CoreDriver
{
    private $session;
    private $client;
    private $forms = array();
    private $started = false;
    private $removeScriptFromUrl = true;
    private $removeHostFromUrl = false;

    /**
     * Initializes BrowserKit driver.
     *
     * @param Client      $client  BrowserKit client instance
     * @param string|null $baseUrl Base URL for HttpKernel clients
     */
    public function __construct(Client $client = null, $baseUrl = null)
    {
        $this->client = $client;
        $this->client->followRedirects(true);

        if ($baseUrl !== null && $client instanceof HttpKernelClient) {
            $client->setServerParameter('SCRIPT_FILENAME', parse_url($baseUrl, PHP_URL_PATH));
        }
    }

    /**
     * Returns BrowserKit HTTP client instance.
     *
     * @return Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Sets driver's current session.
     *
     * @param Session $session
     */
    public function setSession(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Tells driver to remove hostname from URL.
     *
     * @param Boolean $remove
     */
    public function setRemoveHostFromUrl($remove = true)
    {
        $this->removeHostFromUrl = (bool) $remove;
    }

    /**
     * Tells driver to remove scriptname from URL.
     *
     * @param Boolean $remove
     */
    public function setRemoveScriptFromUrl($remove = true)
    {
        $this->removeScriptFromUrl = (bool) $remove;
    }

    /**
     * Starts driver.
     */
    public function start()
    {
        $this->started = true;
    }

    /**
     * Checks whether driver is started.
     *
     * @return Boolean
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * Stops driver.
     */
    public function stop()
    {
        $this->client->restart();
        $this->started = false;
        $this->forms = array();
    }

    /**
     * Resets driver.
     */
    public function reset()
    {
        $this->client->getCookieJar()->clear();
    }

    /**
     * Visit specified URL.
     *
     * @param string $url url of the page
     */
    public function visit($url)
    {
        $this->client->request('GET', $this->prepareUrl($url));
        $this->forms = array();
    }

    /**
     * Returns current URL address.
     *
     * @return string
     *
     * @throws DriverException If the BrowserKit client returns an unsupported request on BrowserKit 2.2.x and older
     */
    public function getCurrentUrl()
    {
        if (method_exists($this->client, 'getInternalRequest')) {
            $request = $this->client->getInternalRequest();
        } else {
            // BC layer for BrowserKit 2.2.x and older
            $request = $this->client->getRequest();

            if (null !== $request && !$request instanceof Request && !$request instanceof HttpFoundationRequest) {
                throw new DriverException(sprintf(
                    'The BrowserKit client returned an unsupported request implementation: %s. Please upgrade your BrowserKit package to 2.3 or newer.',
                    get_class($request)
                ));
            }
        }

        if ($request === null) {
            // If no request exists, return the current
            // URL as null instead of running into a
            // "method on non-object" error.
            return null;
        }

        return $request->getUri();
    }

    /**
     * Reloads current page.
     */
    public function reload()
    {
        $this->client->reload();
        $this->forms = array();
    }

    /**
     * Moves browser forward 1 page.
     */
    public function forward()
    {
        $this->client->forward();
        $this->forms = array();
    }

    /**
     * Moves browser backward 1 page.
     */
    public function back()
    {
        $this->client->back();
        $this->forms = array();
    }

    /**
     * Sets HTTP Basic authentication parameters
     *
     * @param string|Boolean $user     user name or false to disable authentication
     * @param string         $password password
     */
    public function setBasicAuth($user, $password)
    {
        $this->client->setServerParameter('PHP_AUTH_USER', $user);
        $this->client->setServerParameter('PHP_AUTH_PW', $password);
    }

    /**
     * Sets specific request header on client.
     *
     * @param string $name
     * @param string $value
     */
    public function setRequestHeader($name, $value)
    {
        $nameMap = array(
            'accept' => 'HTTP_ACCEPT',
            'accept-charset' => 'HTTP_ACCEPT_CHARSET',
            'accept-encoding' => 'HTTP_ACCEPT_ENCODING',
            'accept-language' => 'HTTP_ACCEPT_LANGUAGE',
            'connection' => 'HTTP_CONNECTION',
            'host' => 'HTTP_HOST',
            'user-agent' => 'HTTP_USER_AGENT',
            'authorization' => 'PHP_AUTH_DIGEST',
        );

        $lowercaseName = strtolower($name);

        if (isset($nameMap[$lowercaseName])) {
            $name = $nameMap[$lowercaseName];
        }

        $this->client->setServerParameter($name, $value);
    }

    /**
     * Returns last response headers.
     *
     * @return array
     */
    public function getResponseHeaders()
    {
        return $this->getResponse()->getHeaders();
    }

    /**
     * Sets cookie.
     *
     * @param string $name
     * @param string $value
     */
    public function setCookie($name, $value = null)
    {
        if (null === $value) {
            $this->deleteCookie($name);

            return;
        }

        $jar = $this->client->getCookieJar();
        $jar->set(new Cookie($name, $value));
    }

    /**
     * Deletes a cookie by name.
     *
     * @param string $name Cookie name.
     */
    protected function deleteCookie($name)
    {
        $path = $this->getCookiePath();
        $jar = $this->client->getCookieJar();

        do {
            if (null !== $jar->get($name, $path)) {
                $jar->expire($name, $path);
            }

            $path = preg_replace('/.$/', '', $path);
        } while ($path);
    }

    /**
     * Returns current cookie path.
     *
     * @return string
     */
    protected function getCookiePath()
    {
        $path = dirname(parse_url($this->getCurrentUrl(), PHP_URL_PATH));

        if ('\\' === DIRECTORY_SEPARATOR) {
            $path = str_replace('\\', '/', $path);
        }

        return $path;
    }

    /**
     * Returns cookie by name.
     *
     * @param string $name
     *
     * @return string|null
     */
    public function getCookie($name)
    {
        // Note that the following doesn't work well because
        // Symfony\Component\BrowserKit\CookieJar stores cookies by name,
        // path, AND domain and if you don't fill them all in correctly then
        // you won't get the value that you're expecting.
        //
        // $jar = $this->client->getCookieJar();
        //
        // if (null !== $cookie = $jar->get($name)) {
        //     return $cookie->getValue();
        // }

        $allValues = $this->client->getCookieJar()->allValues($this->getCurrentUrl());

        if (isset($allValues[$name])) {
            return $allValues[$name];
        } else {
            return null;
        }
    }

    /**
     * Returns last response status code.
     *
     * @return integer
     */
    public function getStatusCode()
    {
        return $this->getResponse()->getStatus();
    }

    /**
     * Returns last response content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->getResponse()->getContent();
    }

    /**
     * Finds elements with specified XPath query.
     *
     * @param string $xpath
     *
     * @return array array of NodeElements
     */
    public function find($xpath)
    {
        $nodes = $this->getCrawler()->filterXPath($xpath);

        $elements = array();
        foreach ($nodes as $i => $node) {
            $elements[] = new NodeElement(sprintf('(%s)[%d]', $xpath, $i + 1), $this->session);
        }

        return $elements;
    }

    /**
     * Returns element's tag name by it's XPath query.
     *
     * @param string $xpath
     *
     * @return string
     */
    public function getTagName($xpath)
    {
        return $this->getCrawlerNode($this->getCrawler()->filterXPath($xpath)->eq(0))->nodeName;
    }

    /**
     * Returns element's text by it's XPath query.
     *
     * @param string $xpath
     *
     * @return string
     */
    public function getText($xpath)
    {
        $text = $this->getCrawler()->filterXPath($xpath)->eq(0)->text();
        $text = str_replace("\n", ' ', $text);
        $text = preg_replace('/ {2,}/', ' ', $text);

        return trim($text);
    }

    /**
     * Returns element's html by it's XPath query.
     *
     * @param string $xpath
     *
     * @return string
     */
    public function getHtml($xpath)
    {
        $node = $this->getCrawlerNode($this->getCrawler()->filterXPath($xpath)->eq(0));
        $text = $node->ownerDocument->saveXML($node);

        // cut the tag itself (making innerHTML out of outerHTML)
        $text = preg_replace('/^\<[^\>]+\>|\<[^\>]+\>$/', '', $text);

        return $text;
    }

    /**
     * Returns element's attribute by it's XPath query.
     *
     * @param string $xpath
     * @param string $name
     *
     * @return string|null
     */
    public function getAttribute($xpath, $name)
    {
        $node = $this->getCrawler()->filterXPath($xpath)->eq(0);

        if ($this->getCrawlerNode($node)->hasAttribute($name)) {
            return $node->attr($name);
        }

        return null;
    }

    /**
     * Returns element's value by it's XPath query.
     *
     * @param string $xpath
     *
     * @return mixed
     */
    public function getValue($xpath)
    {
        if (in_array($this->getAttribute($xpath, 'type'), array('submit', 'image', 'button'))) {
            return $this->getAttribute($xpath, 'value');
        }

        try {
            $field = $this->getFormField($xpath);
        } catch (\InvalidArgumentException $e) {
            return $this->getAttribute($xpath, 'value');
        }

        $value = $field->getValue();

        if ($field instanceof Field\ChoiceFormField && 'checkbox' === $field->getType()) {
            $value = null !== $value;
        }

        return $value;
    }

    /**
     * Sets element's value by it's XPath query.
     *
     * @param string $xpath
     * @param string $value
     */
    public function setValue($xpath, $value)
    {
        $this->getFormField($xpath)->setValue($value);
    }

    /**
     * Checks checkbox by it's XPath query.
     *
     * @param string $xpath
     */
    public function check($xpath)
    {
        $this->getFormField($xpath)->tick();
    }

    /**
     * Unchecks checkbox by it's XPath query.
     *
     * @param string $xpath
     */
    public function uncheck($xpath)
    {
        $this->getFormField($xpath)->untick();
    }

    /**
     * Selects option from select field located by it's XPath query.
     *
     * @param string  $xpath
     * @param string  $value
     * @param Boolean $multiple
     */
    public function selectOption($xpath, $value, $multiple = false)
    {
        $field = $this->getFormField($xpath);

        if ($multiple) {
            $oldValue   = (array) $field->getValue();
            $oldValue[] = $value;
            $value      = $oldValue;
        }

        $field->select($value);
    }

    /**
     * Checks whether select option, located by it's XPath query, is selected.
     *
     * @param string $xpath
     *
     * @return Boolean
     * @throws ElementNotFoundException When element wasn't found
     */
    public function isSelected($xpath)
    {
        if (!count($crawler = $this->getCrawler()->filterXPath($xpath))) {
            throw new ElementNotFoundException($this->session, 'option', 'xpath', $xpath);
        }

        $optionValue = $this->getCrawlerNode($crawler->eq(0))->getAttribute('value');
        $selectField = $this->getFormField('(' . $xpath . ')/ancestor-or-self::*[local-name()="select"]');
        $selectValue = $selectField->getValue();

        return is_array($selectValue) ? in_array($optionValue, $selectValue) : $optionValue == $selectValue;
    }

    /**
     * Clicks button or link located by it's XPath query.
     *
     * @param string $xpath
     *
     * @throws ElementNotFoundException When element wasn't found
     * @throws DriverException When attempted to click on not allowed element
     */
    public function click($xpath)
    {
        if (!count($nodes = $this->getCrawler()->filterXPath($xpath))) {
            throw new ElementNotFoundException($this->session, 'link or button', 'xpath', $xpath);
        }

        $node = $nodes->eq(0);
        $crawlerNode = $this->getCrawlerNode($node);
        $tagName = $crawlerNode->nodeName;

        if ('a' === $tagName) {
            $this->client->click($node->link());
        } elseif ($this->canSubmitForm($crawlerNode)) {
            $this->submit($node->form());
        } elseif ($this->canResetForm($crawlerNode)) {
            $this->resetForm($crawlerNode);
        } else {
            $message = 'BrowserKit driver supports clicking on links and buttons only. But "%s" provided';
            throw new DriverException(sprintf($message, $tagName));
        }
    }

    /**
     * Checks whether checkbox checked located by it's XPath query.
     *
     * @param string $xpath
     *
     * @return Boolean
     */
    public function isChecked($xpath)
    {
        return (bool) $this->getValue($xpath);
    }

    /**
     * Attaches file path to file field located by it's XPath query.
     *
     * @param string $xpath
     * @param string $path
     */
    public function attachFile($xpath, $path)
    {
        $this->getFormField($xpath)->upload($path);
    }

    /**
     * Submits the form.
     *
     * @param string $xpath Xpath.
     *
     * @throws ElementNotFoundException When element wasn't found
     */
    public function submitForm($xpath)
    {
        if (!count($nodes = $this->getCrawler()->filterXPath($xpath))) {
            throw new ElementNotFoundException($this->session, 'form', 'xpath', $xpath);
        }

        $this->submit($nodes->eq(0)->form());
    }

    /**
     * @return Response
     *
     * @throws DriverException If there is not response yet
     */
    protected function getResponse()
    {
        if (!method_exists($this->client, 'getInternalResponse')) {
            $implementationResponse = $this->client->getResponse();

            if (null === $implementationResponse) {
                throw new DriverException('Unable to access the response before visiting a page');
            }

            return $this->convertImplementationResponse($implementationResponse);
        }

        $response = $this->client->getInternalResponse();

        if (null === $response) {
            throw new DriverException('Unable to access the response before visiting a page');
        }

        return $response;
    }

    /**
     * Gets the BrowserKit Response for legacy BrowserKit versions.
     *
     * Before 2.3.0, there was no Client::getInternalResponse method, and the
     * return value of Client::getResponse can be anything when the implementation
     * uses Client::filterResponse because of a bad choice done in BrowserKit and
     * kept for BC reasons (the Client::getInternalResponse method has been added
     * to solve it).
     *
     * This implementation supports client which don't rely Client::filterResponse
     * and clients which use an HttpFoundation Response (like the HttpKernel client).
     *
     * @param object $response the response specific to the BrowserKit implementation
     *
     * @return Response
     *
     * @throws DriverException If the response cannot be converted to a BrowserKit response
     */
    private function convertImplementationResponse($response)
    {
        if ($response instanceof Response) {
            return $response;
        }

        // due to a bug, the HttpKernel client implementation returns the HttpFoundation response
        // The conversion logic is copied from Symfony\Component\HttpKernel\Client::filterResponse
        if ($response instanceof HttpFoundationResponse) {
            $headers = $response->headers->all();
            if ($response->headers->getCookies()) {
                $cookies = array();
                foreach ($response->headers->getCookies() as $cookie) {
                    $cookies[] = new Cookie(
                        $cookie->getName(),
                        $cookie->getValue(),
                        $cookie->getExpiresTime(),
                        $cookie->getPath(),
                        $cookie->getDomain(),
                        $cookie->isSecure(),
                        $cookie->isHttpOnly()
                    );
                }
                $headers['Set-Cookie'] = $cookies;
            }

            // this is needed to support StreamedResponse
            ob_start();
            $response->sendContent();
            $content = ob_get_clean();

            return new Response($content, $response->getStatusCode(), $headers);
        }

        throw new DriverException(sprintf(
            'The BrowserKit client returned an unsupported response implementation: %s. Please upgrade your BrowserKit package to 2.3 or newer.',
            get_class($response)
        ));
    }

    /**
     * Prepares URL for visiting.
     * Removes "*.php/" from urls and then passes it to BrowserKitDriver::visit().
     *
     * @param string $url
     *
     * @return string
     */
    protected function prepareUrl($url)
    {
        $replacement = ($this->removeHostFromUrl ? '' : '$1') . ($this->removeScriptFromUrl ? '' : '$2');

        return preg_replace('#(https?\://[^/]+)(/[^/\.]+\.php)?#', $replacement, $url);
    }

    /**
     * Returns form field from XPath query.
     *
     * @param string $xpath
     *
     * @return FormField
     *
     * @throws ElementNotFoundException
     */
    protected function getFormField($xpath)
    {
        if (!count($crawler = $this->getCrawler()->filterXPath($xpath))) {
            throw new ElementNotFoundException($this->session, 'form field', 'xpath', $xpath);
        }

        $fieldNode = $this->getCrawlerNode($crawler);
        $fieldName = str_replace('[]', '', $fieldNode->getAttribute('name'));

        $formNode = $this->getFormNode($fieldNode);
        $formId = $this->getFormNodeId($formNode);

        // check if form already exists
        if (isset($this->forms[$formId])) {
            if (is_array($this->forms[$formId][$fieldName])) {
                return $this->forms[$formId][$fieldName][$this->getFieldPosition($fieldNode)];
            }

            return $this->forms[$formId][$fieldName];
        }

        // find form button
        if (null === $buttonNode = $this->findFormButton($formNode)) {
            throw new ElementNotFoundException($this->session, 'form submit button for field', 'xpath', $xpath);
        }

        $this->forms[$formId] = new Form($buttonNode, $this->getCurrentUrl());

        if (is_array($this->forms[$formId][$fieldName])) {
            return $this->forms[$formId][$fieldName][$this->getFieldPosition($fieldNode)];
        }

        return $this->forms[$formId][$fieldName];
    }

    /**
     * @param \DOMElement $element
     *
     * @return \DOMElement
     *
     * @throws DriverException if the form node cannot be found
     */
    private function getFormNode(\DOMElement $element)
    {
        if ($element->hasAttribute('form')) {
            $formId = $element->getAttribute('form');
            $formNode = $element->ownerDocument->getElementById($formId);

            if (null === $formNode) {
                throw new DriverException(sprintf('The selected node has an invalid form attribute (%s).', $formId));
            }

            return $formNode;
        }

        $formNode = $element;

        do {
            // use the ancestor form element
            if (null === $formNode = $formNode->parentNode) {
                throw new DriverException('The selected node does not have a form ancestor.');
            }
        } while ('form' !== $formNode->nodeName);

        return $formNode;
    }

    /**
     * Gets the position of the field node among elements with the same name
     *
     * BrowserKit uses the field name as index to find the field in its Form object.
     * When multiple fields have the same name (checkboxes for instance), it will return
     * an array of elements in the order they appear in the DOM.
     *
     * @param \DOMElement $fieldNode
     *
     * @return integer
     */
    private function getFieldPosition(\DOMElement $fieldNode)
    {
        $elements = $this->getCrawler()->filterXPath('//*[@name=\''.$fieldNode->getAttribute('name').'\']');

        if (count($elements) > 1) {
            // more than one element contains this name !
            // so we need to find the position of $fieldNode
            foreach ($elements as $key => $element) {
                if ($element->getNodePath() === $fieldNode->getNodePath()) {
                    return $key;
                }
            }
        }

        return 0;
    }

    private function submit(Form $form)
    {
        $formId = $this->getFormNodeId($form->getFormNode());

        if (isset($this->forms[$formId])) {
            $this->mergeForms($form, $this->forms[$formId]);
        }

        // remove empty file fields from request
        foreach ($form->getFiles() as $name => $field) {
            if (empty($field['name']) && empty($field['tmp_name'])) {
                $form->remove($name);
            }
        }

        $this->client->submit($form);

        $this->forms = array();
    }

    private function resetForm(\DOMElement $fieldNode)
    {
        $formNode = $this->getFormNode($fieldNode);
        $formId = $this->getFormNodeId($formNode);
        unset($this->forms[$formId]);
    }

    /**
     * Determines if a node can submit a form.
     *
     * @param \DOMElement $node Node.
     *
     * @return boolean
     */
    private function canSubmitForm(\DOMElement $node)
    {
        $type = $node->hasAttribute('type') ? $node->getAttribute('type') : null;

        if ('input' == $node->nodeName && in_array($type, array('submit', 'image'))) {
            return true;
        }

        return 'button' == $node->nodeName && (null === $type || 'submit' == $type);
    }

    /**
     * Determines if a node can reset a form.
     *
     * @param \DOMElement $node Node.
     *
     * @return boolean
     */
    private function canResetForm(\DOMElement $node)
    {
        $type = $node->hasAttribute('type') ? $node->getAttribute('type') : null;

        return in_array($node->nodeName, array('input', 'button')) && 'reset' == $type;
    }

    /**
     * Returns form node unique identifier.
     *
     * @param \DOMElement $form
     *
     * @return string
     */
    private function getFormNodeId(\DOMElement $form)
    {
        return md5($form->getLineNo() . $form->getNodePath() . $form->nodeValue);
    }

    /**
     * Finds form submit button inside form node.
     *
     * @param \DOMElement $form
     *
     * @return \DOMElement|null
     */
    private function findFormButton(\DOMElement $form)
    {
        $xpath = new \DOMXPath($form->ownerDocument);

        $buttonXpath = 'descendant::input[not(@form)] | descendant::button[not(@form)]';

        if ($form->hasAttribute('id')) {
            $formId = Crawler::xpathLiteral($form->getAttribute('id'));
            $buttonXpath .= sprintf(' | //input[@form=%s] | //button[@form=%s]', $formId, $formId);
        }

        foreach ($xpath->query($buttonXpath, $form) as $node) {
            if ($this->canSubmitForm($node)) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Merges second form values into first one.
     *
     * @param Form $to   merging target
     * @param Form $from merging source
     */
    private function mergeForms(Form $to, Form $from)
    {
        foreach ($from->all() as $name => $field) {
            $fieldReflection = new \ReflectionObject($field);
            $nodeReflection  = $fieldReflection->getProperty('node');
            $valueReflection = $fieldReflection->getProperty('value');

            $nodeReflection->setAccessible(true);
            $valueReflection->setAccessible(true);

            if (!($field instanceof Field\InputFormField && in_array(
                $nodeReflection->getValue($field)->getAttribute('type'),
                array('submit', 'button', 'image')
            ))) {
                $valueReflection->setValue($to[$name], $valueReflection->getValue($field));
            }
        }
    }

    /**
     * Returns DOMElement from crawler instance.
     *
     * @param Crawler $crawler
     * @param integer $num     number of node from crawler
     *
     * @return \DOMElement|null
     */
    private function getCrawlerNode(Crawler $crawler, $num = 0)
    {
        foreach ($crawler as $i => $node) {
            if ($num == $i) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Returns crawler instance (got from client).
     *
     * @return Crawler
     *
     * @throws DriverException
     */
    private function getCrawler()
    {
        $crawler = $this->client->getCrawler();

        if (null === $crawler) {
            throw new DriverException('Crawler can\'t be initialized. Did you started driver?');
        }

        return $crawler;
    }
}
