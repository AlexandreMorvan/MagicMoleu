<?php

/*
 * Application console fournie à PHPStan (phpstan-symfony) : il connaît ainsi les commandes, leurs arguments et options.
 */

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

return new Application(new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']));
