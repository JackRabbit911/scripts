<?php

function argsManipu(array $argv)
{
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
    
    $actionSch = ['sys', 'app', 'all'];
    $diff = array_diff($argv, $actionSch);
    
    if (!empty($diff) && $argv[0] !== 'all') {
        die('Неизвестный аргумент действия. (Допускаются "sys", "app" или "all")' . PHP_EOL);
    }

    return [$releaseFolder, $subdomains];
}
