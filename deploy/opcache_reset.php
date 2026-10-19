<?php

/**
 * Сброс OPcache через HTTP-endpoint.
 * Вызывается из CLI-скриптов деплоя/отката.
 *
 * @param string $endpoint  URL endpoint
 * @param string $token     Bearer-токен
 * @param int    $retries   Количество попыток
 * @return bool             true если успешно
 */
function resetOpcache(string $endpoint, string $token, int $retries = 3): bool
{
    $success = false;
    for ($i = 0; $i < $retries; $i++) {
        $curlCmd = 'curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 --max-time 10 -H "Authorization: Bearer ' . $token . '" ' . escapeshellarg($endpoint);
        $httpCode = shell_exec($curlCmd);
        $httpCode = trim((string)$httpCode);

        if ($httpCode === '200') {
            echo " -> OPcache сброшен (HTTP $httpCode).\n";
            $success = true;
            break;
        }

        echo " -> Попытка " . ($i + 1) . "/$retries: HTTP $httpCode, повтор через 1с...\n";
        sleep(1);
    }

    if (!$success) {
        echo " -> [Ошибка] Не удалось сбросить OPcache после $retries попыток.\n";
    }

    return $success;
}
