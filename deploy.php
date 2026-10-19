<?php

array_shift($argv);
$env = array_shift($argv) ?? 'test';

switch ($env) {
    case 'test':
        $subdomains = ['test'];
        $releaseFolder = 'test';
        break;
    case 'stage':
        $subdomains = ['stage'];
        $releaseFolder = 'stage';
        break;
    case 'prod':
        $subdomains = ['en', 'de'];
        $releaseFolder = 'production';
        break;
    default:
        die('Неизвестный аргумент окружения. (Допускаются "test", "stage" и "prod")' . PHP_EOL);
}

$actionSch = ['root', 'sys', 'app', 'all'];
$diff = array_diff($argv, $actionSch);

if (!empty($diff) && $argv[0] !== 'all') {
    die('Неизвестный аргумент действия. (Допускаются "root", "sys", "app" или "all")' . PHP_EOL);
}

$actions = empty($argv) ? ['app'] : (in_array('all', $argv) ? ['root', 'sys', 'app'] : $argv);
$isRoot = in_array('root', $actions) ? true : false;
$isSys = in_array('sys', $actions) ? true : false;
$isApp = in_array('app', $actions) ? true : false;

include_once('deploy/dep_lib.php');
include_once('deploy/opcache_reset.php');

$deploy = new Deploy(__DIR__);

$deploy->prepare($subdomains, $isRoot);
$isSys && $deploy->vendor($releaseFolder, $subdomains);
$isApp && $deploy->application($releaseFolder, $subdomains);

resetOpcache('http://localhost/api/deploy/opcache-reset', 'deploy_reset_2026');

echo " -> Деплой завершен!\n";
