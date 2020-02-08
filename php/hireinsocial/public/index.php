<?php

use Symfony\Component\HttpFoundation\Request;
use function App\symfony;
use function HireInSocial\Offers\Infrastructure\bootstrap;

$projectRootPath = dirname(__DIR__);

require $projectRootPath . '/src/autoload.php';

$kernel = symfony(bootstrap($projectRootPath));

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);