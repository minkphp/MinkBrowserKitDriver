<?php

namespace Behat\Mink\Tests\Driver;

use Behat\Mink\Tests\Driver\Basic\IFrameTest;
use Behat\Mink\Tests\Driver\Basic\ScreenshotTest;

abstract class AbstractBrowserKitConfig extends AbstractConfig
{
    final public function __construct()
    {
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        return new static();
    }

    public function skipMessage($testCase, $test): ?string
    {
        if ($testCase === IFrameTest::class) {
            return 'iFrames management is not supported.';
        }

        if ($testCase === ScreenshotTest::class) {
            return 'Screenshots are not supported.';
        }

        return parent::skipMessage($testCase, $test);
    }

    protected function supportsJs(): bool
    {
        return false;
    }
}
