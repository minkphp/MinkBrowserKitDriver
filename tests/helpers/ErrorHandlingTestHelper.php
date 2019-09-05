<?php

namespace Behat\Mink\Tests\Driver\Custom;

use Behat\Mink\Tests\Driver\DoRequestReturnObject;

if (PHP_VERSION_ID >= 70200) {
    require_once __DIR__ . '/DoRequest72.php';
} else {
    require_once __DIR__ . '/DoRequest5.php';
}

class TestClient extends DoRequestReturnObject {}
