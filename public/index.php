<?php

/**
 * File Image Manager v1.0.0 - Front Controller
 *
 * All requests are routed through this file.
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use RFM\App;

$app = new App(configPath: __DIR__ . '/../config/filemanager.php');
$app->run();
