<?php

declare(strict_types=1);

use Maximaster\BitrixSqlProfile\SqlProfiler;

require_once __DIR__ . '/../src/SqlProfiler.php';

if (defined('BITRIX_SQL_PROFILE_VALUE') === false) {
    return;
}

$usedTriggers = defined('BITRIX_SQL_PROFILE_TRIGGERS')
    ? constant('BITRIX_SQL_PROFILE_TRIGGERS')
    : ['_GET', '_POST', '_COOKIE', '_ENV'];

$triggerName = defined('BITRIX_SQL_PROFILE_TRIGGER_NAME')
    ? constant('BITRIX_SQL_PROFILE_TRIGGER_NAME')
    : 'BITRIX_SQL_PROFILE';

$expectedTriggerValue = constant('BITRIX_SQL_PROFILE_VALUE');

SqlProfiler::if(function () use ($usedTriggers, $expectedTriggerValue, $triggerName) {
    foreach ($usedTriggers as $usedTrigger) {
        if ($usedTrigger === '_SESSION' && session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (($GLOBALS[$triggerName] ?? null) === $expectedTriggerValue) {
            return true;
        }

        if ($usedTrigger === '_ENV' && getenv($triggerName) === $expectedTriggerValue) {
            return true;
        }
    }

    return false;
});
