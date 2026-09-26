<?php

declare(strict_types=1);

/**
 * Front controller. Every HTTP request enters here.
 */

define('ORIN_BASE', dirname(__DIR__));

require ORIN_BASE . '/vendor/autoload.php';

use Orin\Core\App;

$app = App::boot(ORIN_BASE);

$app->run();
