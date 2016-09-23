<?php

$loader = require dirname(__DIR__) . '/vendor/autoload.php';
/** @var $loader \Composer\Autoload\ClassLoader */
$loader->addPsr4('Ttskch\TwiggedSwiftMessageBuilder\\', __DIR__);
