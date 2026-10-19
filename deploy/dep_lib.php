<?php

class Deploy
{
    private string $appRepo = "https://github.com/JackRabbit911/burime.git";
    private string $rootRepo = "https://github.com/JackRabbit911/root.git";

    public function __construct(
        private string $docroot,
        private string $releaseFolder,
        private array $subdomains) {}

    public function prepare(bool $pull)
    {
        echo " -> Git clone/pull, create necessary folders...\n";

        foreach ($this->subdomains as $subdomain) {
            chdir($this->docroot);
            $folder = "$this->docroot/$subdomain";
            $updateSymlink = false;

            if (!is_dir($folder)) {
                $this->cloneRoot($subdomain);
                $updateSymlink = true;
            } elseif ($pull) {
                $this->pullRoot($subdomain);
                $updateSymlink = true;
            }

            if ($updateSymlink) {
                $this->updateSymlink($this->docroot, $subdomain);
            }

            $storage = "$folder/storage";

            if (!is_dir($storage)) {
                mkdir($storage, 0777, true);
            }

            chmod($storage, 0777);
            $logs = "$storage/logs";

            if (!is_dir($logs)) {
                mkdir($logs, 0777, true);
            }

            chmod($logs, 0777);
        }
    }

    public function application(): void
    {
        $relationReleasesDir = "releases/application/$this->releaseFolder";
        $releaseDir = "$this->docroot/$relationReleasesDir";
        $timestamp = date('YmdHis');
        $newRelease = "$releaseDir/$timestamp";

        echo " -> Сборка релиза application: $timestamp\n";

        if (!is_dir($newRelease)) {
            mkdir($newRelease, 0755, true);
        }

        $branch = 'master';

        echo " -> Git clone --branch $branch...\n";
        $gitCmd = "git clone --depth 1 --branch " . escapeshellarg($branch) . " " . escapeshellarg($this->appRepo) . " " . escapeshellarg($newRelease) . " 2>&1";
        exec($gitCmd, $output, $resultCode);

        if ($resultCode !== 0) {
            die(" -> [Ошибка] Не удалось клонировать репозиторий.\n");
        } else {
            shell_exec("rm -rf " . escapeshellarg("$newRelease/.git"));
        }

        $this->cleanupReleases($releaseDir);

        foreach ($this->subdomains as $subdomain) {
            chdir("$this->docroot/$subdomain");
            @unlink('application');
            symlink("../$relationReleasesDir/$timestamp", 'application');
        }

        chdir($this->docroot);

        echo " -> Application обновлён. Релиз: $timestamp\n";
    }

    public function vendor(): void
    {
        switch ($this->releaseFolder) {
            case 'production':
                $composerJson = 'composer.json';
                $composerLock = 'composer.lock';
                $noDev = ' --no-dev';
                break;
            default:
                $composerJson = 'composer_dev.json';
                $composerLock = 'composer_dev.lock';
                $noDev = '';
        }

        $relationReleasesDir = "releases/system/$this->releaseFolder";
        $releaseDir = "$this->docroot/$relationReleasesDir";
        $scriptDir = "$this->docroot/deploy";
        $timestamp = date('YmdHis');
        $newRelease = "$releaseDir/$timestamp";
        $keepReleases = 3;

        echo " -> Сборка релиза фреймворка: $timestamp\n";

        if (!is_dir($newRelease)) {
            mkdir($newRelease, 0755, true);
        }

        if (!file_exists("$scriptDir/composer.phar")) {
            die(" -> [Ошибка] Файл composer.phar не найден в $scriptDir\n");
        }

        if (!file_exists("$scriptDir/$composerJson")) {
            die(" -> [Ошибка] Файл $composerJson не найден в $scriptDir\n");
        }

        copy("$scriptDir/composer.phar", "$newRelease/composer.phar");
        copy("$scriptDir/$composerJson", "$newRelease/composer.json");
        is_file("$scriptDir/$composerLock") && copy("$scriptDir/$composerLock", "$newRelease/composer.lock");

        $releases = $this->cleanupReleases($releaseDir, $keepReleases);

        if (!empty($releases)) {
            $lastRelease = end($releases);

            if (is_dir("$lastRelease/vendor")) {
                // Используем системный cp для быстрого рекурсивного копирования папки
                shell_exec("cp -rp " . escapeshellarg("$lastRelease/vendor") . " " . escapeshellarg($newRelease));
            }
        }

        echo " -> Запуск composer install...\n";
        chdir($newRelease);
        $composerCmd = 'php ' . escapeshellarg("$newRelease/composer.phar") . " install$noDev --optimize-autoloader --no-interaction 2>&1";
        exec($composerCmd, $output, $resultCode);

        // Если install упал (рассинхрон lock/json), пробуем update
        if ($resultCode !== 0) {
            echo " -> Composer install failed, trying update...\n";
            chdir($newRelease);
            $composerCmd = 'php ' . escapeshellarg("$newRelease/composer.phar") . " update --no-dev --optimize-autoloader --no-interaction 2>&1";
            exec($composerCmd, $output, $resultCode);
        }

        if ($resultCode !== 0) {
            die(" -> [Ошибка] Composer завершил работу с ошибкой: $resultCode\n");
        }

        foreach ($this->subdomains as $subdomain) {
            chdir("$this->docroot/$subdomain");
            @unlink('vendor');
            symlink("../$relationReleasesDir/$timestamp/vendor", 'vendor');
        }

        echo " -> Фреймворк обновлён. Релиз $timestamp\n";
    }

    private function cleanupReleases(string $releaseDir, int $keepReleases = 3): array
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
                echo " -> Удаление старого релиза: " . basename($releases[$i]) . "\n";
                shell_exec("rm -rf " . escapeshellarg($releases[$i]));
            }
        }

        return $releases;
    }

    private function cloneRoot(string $subdomain)
    {
        $gitCmd = "git clone $this->rootRepo $subdomain";

        exec($gitCmd, $output, $resultCode);

        if ($resultCode !== 0) {
            echo "Не удалось клонировать репозиторий\n";
            print_r($output);
            exit;
        }
    }

    private function pullRoot(string $subdomain)
    {
        chdir($subdomain);

        if (!is_dir('.git')) {
            die("Репозиторий не найден");
        }

        $gitCmd = "git pull";
        exec($gitCmd, $output, $resultCode);

        if ($resultCode !== 0) {
            print_r($output);
            exit;
        }
    }

    private function updateSymlink(string $docroot, string $subdomain)
    {
        chdir("$docroot/htdocs");

        $target = "../$subdomain/public";
        $symlink = "$subdomain";

        @unlink($symlink);

        if (!symlink($target, $symlink)) {
            die("Не удалось создать симлинк");
        }

        chdir($docroot);
    }
}

function getDeployClass(string $docroot, string $releaseFolder, array $subdomains)
{
    return new Deploy($docroot, $releaseFolder, $subdomains);
}
