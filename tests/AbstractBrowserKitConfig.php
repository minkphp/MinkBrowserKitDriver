<?php

namespace Behat\Mink\Tests\Driver;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Tests\Driver\Util\FixturesKernel;
use Symfony\Component\HttpKernel\Client;

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

    public function skipMessage($testCase, $test)
    {
        if (
            'Behat\Mink\Tests\Driver\Form\Html5Test' === $testCase
            && in_array($test, array(
                'testHtml5FormAction',
                'testHtml5FormMethod',
            ))
            && !class_exists('\Symfony\Component\DomCrawler\AbstractUriElement')
        ) {
            return 'Mink BrowserKit doesn\'t support HTML5 form attributes before Symfony 3.3';
        }

        return parent::skipMessage($testCase, $test);
    }
}
