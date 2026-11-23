<?php

declare(strict_types=1);

$standalone = __DIR__ . '/../vendor/autoload.php';
$loader = require is_file($standalone) ? $standalone : __DIR__ . '/../../../autoload.php';
$loader->addPsr4('DrupalEnv\\Tests\\', __DIR__);
