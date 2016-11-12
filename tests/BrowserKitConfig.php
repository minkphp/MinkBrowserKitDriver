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

    public function skipMessage($testCase, $test)
    {
        if ('Behat\Mink\Tests\Driver\Form\Html5Test' === $testCase && 'testHtml5FormAction' === $test && version_compare($this->getDomCrawlerVersion(), '3.3.0.0-dev', '<')) {
            return 'Symfony DomCrawler < 3.3 does not support the formAction attribute.';
        }

        return parent::skipMessage($testCase, $test);
    }

    private function getDomCrawlerVersion()
    {
        $installedFile = __DIR__.'/../vendor/composer/installed.json';
        $installedPackages = json_decode(file_get_contents($installedFile), true);

        foreach ($installedPackages as $installedPackage) {
            if ('symfony/dom-crawler' !== $installedPackage['name'] && 'symfony/symfony' !== $installedPackage['name']) {
                continue;
            }

            return $installedPackage['version_normalized'];
        }

        throw new \RuntimeException('Unable to determine the symfony/dom-crawler version');
    }
}
