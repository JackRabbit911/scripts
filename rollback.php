<?php

include_once('deploy/args.php');
[$releaseFolder, $subdomains] = argsManipu($argv);

$actions = empty($argv) ? ['app'] : (in_array('all', $argv) ? ['sys', 'app'] : $argv);
$isSys = in_array('sys', $actions) ? true : false;
$isApp = in_array('app', $actions) ? true : false;

$rollback = new RollBack(__DIR__, $releaseFolder, $subdomains);

$isApp && $rollback->application();
$isSys && $rollback->vendor();

include_once('deploy/opcache_reset.php');
resetOpcache('http://localhost/api/deploy/opcache-reset', 'deploy_reset_2026');

echo "-> Откат успешно завершен!\n";

class RollBack
{
    public function __construct(
        private string $docroot,
        private string $releaseFolder,
        private array $subdomains
    ) {}

    public function application()
    {
        $linkPath = "$this->docroot/{$this->subdomains[0]}/application";;
        $relativeReleaseDir = "../releases/application/$this->releaseFolder";
    
        $previousReleaseName = $this->getPreviosRelease($linkPath, 'application');

        if (!$previousReleaseName) {
            return;
        }
    
        foreach($this->subdomains as $subdomain) {
            chdir("$this->docroot/$subdomain");
            @unlink('application');
            symlink("$relativeReleaseDir/$previousReleaseName", 'application');
        }
    
        chdir($this->docroot);
    }
    
    public function vendor()
    {
        $linkPath = "$this->docroot/{$this->subdomains[0]}/vendor";
        $relativeReleaseDir = "../releases/system/$this->releaseFolder";
    
        $previousReleaseName = $this->getPreviosRelease($linkPath, 'system');

        if (!$previousReleaseName) {
            return;
        }
    
        foreach($this->subdomains as $subdomain) {
            chdir("$this->docroot/$subdomain");
            @unlink('vendor');
            symlink("$relativeReleaseDir/$previousReleaseName/vendor", 'vendor');
        }
    
        chdir($this->docroot);
    }
    
    private function getPreviosRelease(string $linkPath, string $segment)
    {
        $releaseDir = "$this->docroot/releases/$segment/$this->releaseFolder";
        $currentReleasePath = is_link($linkPath) ? readlink($linkPath) : '';
        $pattern = ['/^../', '/\/vendor$/'];
        $replace = [$this->docroot, ''];
        $currentReleaseDir = preg_replace($pattern, $replace, $currentReleasePath);
    
        $releases = glob("$releaseDir/*", GLOB_ONLYDIR);

        if (count($releases) < 2) {
            echo(" -> [Пропуск] Не найдено предыдущих релизов для отката (всего релизов: " . count($releases) . ").\n");
            return false;
        }
    
        usort($releases, function ($a, $b) {
            return filemtime($a) - filemtime($b);
        });
    
        $previousRelease = null;
        foreach ($releases as $key => $release) {
            if ($release === $currentReleaseDir) {
                if ($key > 0) {
                    $previousRelease = $releases[$key - 1];
                }
                break;
            }
        }
    
        if (!$previousRelease) {
            $previousRelease = $releases[count($releases) - 2];
        }
    
        $previousReleaseName = basename($previousRelease);
        echo " -> Обнаружен прошлый рабочий релиз: $previousReleaseName\n";
        echo " -> Удаляем: $currentReleaseDir\n";
        shell_exec('rm -rf ' . escapeshellarg($currentReleaseDir));
    
        return $previousReleaseName;
    }
}
