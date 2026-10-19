<?php

include_once('deploy/args.php');
[$releaseFolder, $subdomains] = argsManipu($argv);

$actions = empty($argv) ? ['app'] : (in_array('all', $argv) ? ['root', 'sys', 'app'] : $argv);
$isRoot = in_array('root', $actions) ? true : false;
$isSys = in_array('sys', $actions) ? true : false;
$isApp = in_array('app', $actions) ? true : false;

include_once('deploy/dep_lib.php');
include_once('deploy/opcache_reset.php');

$deploy = getDeployClass(__DIR__, $releaseFolder, $subdomains);

$deploy->prepare($isRoot);
$isSys && $deploy->vendor();
$isApp && $deploy->application();

resetOpcache('http://localhost/api/deploy/opcache-reset', 'deploy_reset_2026');

echo " -> Деплой завершен!\n";
