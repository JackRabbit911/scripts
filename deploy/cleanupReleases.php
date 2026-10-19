<?php

function cleanupReleases(string $releaseDir, int $keepReleases = 3)
{
    $releases = glob("$releaseDir/*", GLOB_ONLYDIR);
    
    if (count($releases) > $keepReleases) {
        // Сортируем по времени изменения (старые в начале)
        usort($releases, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // Вычисляем сколько папок нужно удалить
        $toDelete = count($releases) - $keepReleases;
        for ($i = 0; $i < $toDelete; $i++) {
            echo "    Удаление старого релиза: " . basename($releases[$i]) . "\n";
            shell_exec("rm -rf " . escapeshellarg($releases[$i]));
        }
    }

    return $releases;
}
