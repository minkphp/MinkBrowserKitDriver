<?php

/*
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Mink\Driver;

use Behat\Mink\Exception\DriverException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\BrowserKit\Exception\BadMethodCallException;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\DomCrawler\Field\InputFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

/**
 * Symfony BrowserKit driver.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class BrowserKitDriver extends CoreDriver
{
    /**
     * @var AbstractBrowser
     */
    private $client;

    /**
     * @var array<string, Form>
     */
    private $forms = array();
    /**
     * @var array<string, string>
     */
    private $serverParameters = array();
    /**
     * @var bool
     */
    private $started = false;

    /**
     * Initializes BrowserKit driver.
     *
     * @param string|null $baseUrl Base URL for HttpKernel clients
     */
    public function __construct(AbstractBrowser $client, ?string $baseUrl = null)
    {
        $this->client = $client;
        $this->client->followRedirects(true);

        if ($baseUrl !== null && $client instanceof HttpKernelBrowser) {
            $basePath = parse_url($baseUrl, PHP_URL_PATH);

            if (\is_string($basePath)) {
                $client->setServerParameter('SCRIPT_FILENAME', $basePath);
            }
        }
    }

    /**
     * Returns BrowserKit browser instance.
     *
     * @return AbstractBrowser
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->started = true;
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return $this->started;
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->reset();
        $this->started = false;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        // Restarting the client resets the cookies and the history
        $this->client->restart();
        $this->forms = array();
        $this->serverParameters = array();
    }

    /**
     * {@inheritdoc}
     */
    public function visit(string $url)
    {
        $this->client->request('GET', $this->prepareUrl($url), array(), array(), $this->serverParameters);
        $this->forms = array();
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentUrl()
    {
        // This should be encapsulated in `getRequest` method if any other method needs the request
        try {
            $request = $this->client->getInternalRequest();
        } catch (BadMethodCallException $e) {
            // Handling Symfony 5+ behaviour
            $request = null;
        }

        if ($request === null) {
            throw new DriverException('Unable to access the request before visiting a page');
        }

        return $request->getUri();
    }

    /**
     * {@inheritdoc}
     */
    public function reload()
    {
        $this->client->reload();
        $this->forms = array();
    }

    /**
     * {@inheritdoc}
     */
    public function forward()
    {
        $this->client->forward();
        $this->forms = array();
    }

    /**
     * {@inheritdoc}
     */
    public function back()
    {
        $this->client->back();
        $this->forms = array();
    }

    /**
     * {@inheritdoc}
     */
    public function setBasicAuth($user, string $password)
    {
        if (false === $user) {
            unset($this->serverParameters['PHP_AUTH_USER'], $this->serverParameters['PHP_AUTH_PW']);
            unset($this->serverParameters['HTTP_AUTHORIZATION']);

            return;
        }

        $this->serverParameters['PHP_AUTH_USER'] = $user;
        $this->serverParameters['PHP_AUTH_PW'] = $password;
        $this->serverParameters['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($user . ':' . $password);
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestHeader(string $name, string $value)
    {
        $contentHeaders = array('CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true);
        $name = str_replace('-', '_', strtoupper($name));

        // CONTENT_* are not prefixed with HTTP_ in PHP when building $_SERVER
        if (!isset($contentHeaders[$name])) {
            $name = 'HTTP_' . $name;
        }

        $this->serverParameters[$name] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseHeaders()
    {
        return $this->getResponse()->getHeaders();
    }

    /**
     * {@inheritdoc}
     */
    public function setCookie(string $name, ?string $value = null)
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
    private function deleteCookie(string $name): void
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
     */
    private function getCookiePath(): string
    {
        $path = parse_url($this->getCurrentUrl(), PHP_URL_PATH);

        if ($path === null || $path === false || $path === '') {
            $path = '/';
        }

        if ('\\' === DIRECTORY_SEPARATOR) {
            $path = str_replace('\\', '/', $path);
        }

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookie(string $name)
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
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        $response = $this->getResponse();

        return $response->getStatusCode();
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return $this->getResponse()->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function findElementXpaths(string $xpath)
    {
        $nodes = $this->getCrawler()->filterXPath($xpath);

        $elements = array();
        foreach ($nodes as $i => $node) {
            $elements[] = sprintf('(%s)[%d]', $xpath, $i + 1);
        }

        return $elements;
    }

    /**
     * {@inheritdoc}
     */
    public function getTagName(string $xpath)
    {
        return $this->getCrawlerNode($this->getFilteredCrawler($xpath))->nodeName;
    }

    /**
     * {@inheritdoc}
     */
    public function getText(string $xpath)
    {
        $text = $this->getFilteredCrawler($xpath)->text(null, true);

        return $text;
    }

    /**
     * {@inheritdoc}
     */
    public function getHtml(string $xpath)
    {
        return $this->getFilteredCrawler($xpath)->html();
    }

    /**
     * {@inheritdoc}
     */
    public function getOuterHtml(string $xpath)
    {
        $crawler = $this->getFilteredCrawler($xpath);

        return $crawler->outerHtml();
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute(string $xpath, string $name)
    {
        $node = $this->getFilteredCrawler($xpath);

        if ($this->getCrawlerNode($node)->hasAttribute($name)) {
            return $node->attr($name);
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(string $xpath)
    {
        if (in_array($this->getAttribute($xpath, 'type'), array('submit', 'image', 'button'), true)) {
            return $this->getAttribute($xpath, 'value');
        }

        $node = $this->getCrawlerNode($this->getFilteredCrawler($xpath));

        if ('option' === $node->tagName) {
            return $this->getOptionValue($node);
        }

        try {
            $field = $this->getFormField($xpath);
        } catch (\InvalidArgumentException $e) {
            return $this->getAttribute($xpath, 'value');
        }

        $value = $field->getValue();

        if ('select' === $node->tagName && null === $value) {
            // symfony/dom-crawler returns null as value for a non-multiple select without
            // options but we want an empty string to match browsers.
            $value = '';
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(string $xpath, $value)
    {
        $field = $this->getFormField($xpath);

        if ($field instanceof ChoiceFormField) {
            if (!\is_string($value) && $field->getType() === 'radio') {
                throw new DriverException('Only string values can be used for a radio input.');
            }

            if (\is_bool($value) && $field->getType() === 'select') {
                throw new DriverException('Boolean values cannot be used for a select element.');
            }

            $field->setValue($value);
            return;
        }

        if (\is_array($value) || \is_bool($value)) {
            throw new DriverException('Textual and file form fields don\'t support array or boolean values.');
        }

        $field->setValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function check(string $xpath)
    {
        $this->getCheckboxField($xpath)->tick();
    }

    /**
     * {@inheritdoc}
     */
    public function uncheck(string $xpath)
    {
        $this->getCheckboxField($xpath)->untick();
    }

    /**
     * {@inheritdoc}
     */
    public function selectOption(string $xpath, string $value, bool $multiple = false)
    {
        $field = $this->getFormField($xpath);

        if (!$field instanceof ChoiceFormField) {
            throw new DriverException(sprintf('Impossible to select an option on the element with XPath "%s" as it is not a select or radio input', $xpath));
        }

        if ($multiple) {
            $oldValue   = (array) $field->getValue();
            $oldValue[] = $value;
            $value      = $oldValue;
        }

        $field->select($value);
    }

    /**
     * {@inheritdoc}
     */
    public function isSelected(string $xpath)
    {
        $optionValue = $this->getOptionValue($this->getCrawlerNode($this->getFilteredCrawler($xpath)));
        $selectField = $this->getFormField('(' . $xpath . ')/ancestor-or-self::*[local-name()="select"]');
        $selectValue = $selectField->getValue();

        return is_array($selectValue) ? in_array($optionValue, $selectValue, true) : $optionValue === $selectValue;
    }

    /**
     * {@inheritdoc}
     */
    public function click(string $xpath)
    {
        $crawler = $this->getFilteredCrawler($xpath);
        $node = $this->getCrawlerNode($crawler);
        $tagName = $node->nodeName;

        if ('a' === $tagName) {
            $this->client->click($crawler->link());
            $this->forms = array();
        } elseif ($this->canSubmitForm($node)) {
            $this->submit($crawler->form());
        } elseif ($this->canResetForm($node)) {
            $this->resetForm($node);
        } else {
            $message = sprintf('%%s supports clicking on links and submit or reset buttons only. But "%s" provided', $tagName);

            throw new UnsupportedDriverActionException($message, $this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isChecked(string $xpath)
    {
        $field = $this->getFormField($xpath);

        if (!$field instanceof ChoiceFormField || 'select' === $field->getType()) {
            throw new DriverException(sprintf('Impossible to get the checked state of the element with XPath "%s" as it is not a checkbox or radio input', $xpath));
        }

        if ('checkbox' === $field->getType()) {
            return $field->hasValue();
        }

        $radio = $this->getCrawlerNode($this->getFilteredCrawler($xpath));

        return $radio->getAttribute('value') === $field->getValue();
    }

    /**
     * {@inheritdoc}
     */
    public function attachFile(string $xpath, string $path)
    {
        $field = $this->getFormField($xpath);

        if (!$field instanceof FileFormField) {
            throw new DriverException(sprintf('Impossible to attach a file on the element with XPath "%s" as it is not a file input', $xpath));
        }

        $field->upload($path);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(string $xpath)
    {
        $crawler = $this->getFilteredCrawler($xpath);

        $this->submit($crawler->form());
    }

    /**
     * @return Response
     *
     * @throws DriverException If there is not response yet
     */
    protected function getResponse()
    {
        try {
            $response = $this->client->getInternalResponse();
        } catch (BadMethodCallException $e) {
            // Handling Symfony 5+ behaviour
            $response = null;
        }

        if (null === $response) {
            throw new DriverException('Unable to access the response before visiting a page');
        }

        return $response;
    }

    /**
     * Prepares URL for visiting.
     * Removes "*.php/" from urls and then passes it to BrowserKitDriver::visit().
     *
     * @param string $url
     *
     * @return string
     */
    protected function prepareUrl(string $url)
    {
        return $url;
    }

    /**
     * Returns form field from XPath query.
     *
     * @param string $xpath
     *
     * @return FormField
     *
     * @throws DriverException
     * @throws \InvalidArgumentException when the field does not exist in the BrowserKit form
     */
    protected function getFormField(string $xpath)
    {
        $fieldNode = $this->getCrawlerNode($this->getFilteredCrawler($xpath));
        $fieldType = $fieldNode->getAttribute('type');

        if (\in_array($fieldType, ['button', 'submit', 'image'], true)) {
            throw new DriverException(sprintf('Cannot access a form field of type "%s".', $fieldType));
        }

        $fieldName = str_replace('[]', '', $fieldNode->getAttribute('name'));

        $formNode = $this->getFormNode($fieldNode);
        $formId = $this->getFormNodeId($formNode);

        if (!isset($this->forms[$formId])) {
            $this->forms[$formId] = new Form($formNode, $this->getCurrentUrl());
        }

        if (is_array($this->forms[$formId][$fieldName])) {
            $positionField = $this->forms[$formId][$fieldName][$this->getFieldPosition($fieldNode)];

            \assert($positionField instanceof FormField);

            return $positionField;
        }

        return $this->forms[$formId][$fieldName];
    }

    /**
     * Returns the checkbox field from xpath query, ensuring it is valid.
     *
     * @param string $xpath
     *
     * @return ChoiceFormField
     *
     * @throws DriverException when the field is not a checkbox
     */
    private function getCheckboxField(string $xpath): ChoiceFormField
    {
        $field = $this->getFormField($xpath);

        if (!$field instanceof ChoiceFormField) {
            throw new DriverException(sprintf('Impossible to check the element with XPath "%s" as it is not a checkbox', $xpath));
        }

        return $field;
    }

    /**
     * @param \DOMElement $element
     *
     * @return \DOMElement
     *
     * @throws DriverException if the form node cannot be found
     */
    private function getFormNode(\DOMElement $element): \DOMElement
    {
        if ($element->hasAttribute('form')) {
            $formId = $element->getAttribute('form');
            \assert($element->ownerDocument !== null);
            $formNode = $element->ownerDocument->getElementById($formId);

            if (null === $formNode || 'form' !== $formNode->nodeName) {
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

        \assert($formNode instanceof \DOMElement);

        return $formNode;
    }

    /**
     * Gets the position of the field node among elements with the same name
     *
     * BrowserKit uses the field name as index to find the field in its Form object.
     * When multiple fields have the same name (checkboxes for instance), it will return
     * an array of elements in the order they appear in the DOM.
     *
     * @throws DriverException
     */
    private function getFieldPosition(\DOMElement $fieldNode): int
    {
        $elements = $this->getCrawler()->filterXPath('//*[@name=\''.$fieldNode->getAttribute('name').'\']');

        if (count($elements) > 1) {
            // more than one element contains this name !
            // so we need to find the position of $fieldNode
            foreach ($elements as $key => $element) {
                /** @var \DOMElement $element */
                if ($element->getNodePath() === $fieldNode->getNodePath()) {
                    return $key;
                }
            }
        }

        return 0;
    }

    private function submit(Form $form): void
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

        $this->client->submit($form, array(), $this->serverParameters);

        $this->forms = array();
    }

    private function resetForm(\DOMElement $fieldNode): void
    {
        $formNode = $this->getFormNode($fieldNode);
        $formId = $this->getFormNodeId($formNode);
        unset($this->forms[$formId]);
    }

    private function canSubmitForm(\DOMElement $node): bool
    {
        $type = $node->hasAttribute('type') ? $node->getAttribute('type') : null;

        if ('input' === $node->nodeName && in_array($type, array('submit', 'image'), true)) {
            return true;
        }

        return 'button' === $node->nodeName && (null === $type || 'submit' === $type);
    }

    private function canResetForm(\DOMElement $node): bool
    {
        $type = $node->hasAttribute('type') ? $node->getAttribute('type') : null;

        return in_array($node->nodeName, array('input', 'button'), true) && 'reset' === $type;
    }

    /**
     * Returns form node unique identifier.
     *
     * @param \DOMElement $form
     *
     * @return string
     */
    private function getFormNodeId(\DOMElement $form): string
    {
        return md5($form->getLineNo() . $form->getNodePath() . $form->nodeValue);
    }

    /**
     * Gets the value of an option element
     *
     * @param \DOMElement $option
     *
     * @return string
     *
     * @see \Symfony\Component\DomCrawler\Field\ChoiceFormField::buildOptionValue
     */
    private function getOptionValue(\DOMElement $option): string
    {
        if ($option->hasAttribute('value')) {
            return $option->getAttribute('value');
        }

        if (!empty($option->nodeValue)) {
            return $option->nodeValue;
        }

        return '1'; // DomCrawler uses 1 by default if there is no text in the option
    }

    /**
     * Merges second form values into first one.
     *
     * @param Form $to   merging target
     * @param Form $from merging source
     */
    private function mergeForms(Form $to, Form $from): void
    {
        foreach ($from->all() as $name => $field) {
            $fieldReflection = new \ReflectionObject($field);
            $nodeReflection  = $fieldReflection->getProperty('node');
            $valueReflection = $fieldReflection->getProperty('value');

            $nodeReflection->setAccessible(true);
            $valueReflection->setAccessible(true);

            $isIgnoredField = $field instanceof InputFormField &&
                in_array($nodeReflection->getValue($field)->getAttribute('type'), array('submit', 'button', 'image'), true);

            if (!$isIgnoredField) {
                $targetField = $to[$name];

                \assert($targetField instanceof FormField);

                $valueReflection->setValue($targetField, $valueReflection->getValue($field));
            }
        }
    }

    /**
     * Returns DOMElement from crawler instance.
     *
     * @throws DriverException when the node does not exist
     */
    private function getCrawlerNode(Crawler $crawler): \DOMElement
    {
        $node = $crawler->getNode(0);

        if (null !== $node) {
            \assert($node instanceof \DOMElement);

            return $node;
        }

        throw new DriverException('The element does not exist');
    }

    /**
     * Returns a crawler filtered for the given XPath, requiring at least 1 result.
     *
     * @param string $xpath
     *
     * @return Crawler
     *
     * @throws DriverException when no matching elements are found
     */
    private function getFilteredCrawler(string $xpath): Crawler
    {
        if (!count($crawler = $this->getCrawler()->filterXPath($xpath))) {
            throw new DriverException(sprintf('There is no element matching XPath "%s"', $xpath));
        }

        return $crawler;
    }

    /**
     * Returns crawler instance (got from client).
     *
     * @return Crawler
     *
     * @throws DriverException
     */
    private function getCrawler(): Crawler
    {
        try {
            $crawler = $this->client->getCrawler();
        } catch (BadMethodCallException $e) {
            $crawler = null;
        }

        if (null === $crawler) {
            throw new DriverException('Unable to access the response content before visiting a page');
        }

        return $crawler;
    }
}
