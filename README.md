Mink BrowserKit Driver
======================

[![Latest Stable Version](https://poser.pugx.org/behat/mink-browserkit-driver/v/stable.png)](https://packagist.org/packages/behat/mink-browserkit-driver)
[![Latest Unstable Version](https://poser.pugx.org/behat/mink-browserkit-driver/v/unstable.svg)](https://packagist.org/packages/behat/mink-browserkit-driver)
[![Total Downloads](https://poser.pugx.org/behat/mink-browserkit-driver/downloads.png)](https://packagist.org/packages/behat/mink-browserkit-driver)
[![CI](https://github.com/minkphp/MinkBrowserKitDriver/actions/workflows/tests.yml/badge.svg)](https://github.com/minkphp/MinkBrowserKitDriver/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/behat/mink-browserkit-driver/license.svg)](https://packagist.org/packages/behat/mink-browserkit-driver)
[![codecov](https://codecov.io/gh/minkphp/MinkBrowserKitDriver/branch/master/graph/badge.svg?token=sECxcowuiJ)](https://codecov.io/gh/minkphp/MinkBrowserKitDriver)

Usage Example
-------------

``` php
<?php

use Behat\Mink\Mink,
    Behat\Mink\Session,
    Behat\Mink\Driver\BrowserKitDriver;

use Symfony\Component\HttpKernel\Client;

$app  = require_once(__DIR__.'/app.php'); // Silex app

$mink = new Mink(array(
    'silex' => new Session(new BrowserKitDriver(new Client($app))),
));

$mink->getSession('silex')->getPage()->findLink('Chat')->click();
```

Installation
------------

To install use the `composer require` command:

```bash
composer require --dev behat/mink behat/mink-browserkit-driver
```

Maintainers
-----------

* Christophe Coevoet [stof](https://github.com/stof)
* Other [awesome developers](https://github.com/minkphp/MinkBrowserKitDriver/graphs/contributors)
