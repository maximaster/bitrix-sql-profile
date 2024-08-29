<?php

declare(strict_types=1);

namespace Maximaster\BitrixSqlProfile;

use Bitrix\Main\Application;
use Bitrix\Main\Diag\SqlTracker;
use Bitrix\Main\Loader;
use Bitrix\Main\LoaderException;
use CPerfomanceKeeper;

class SqlProfiler
{
    private SqlTracker $sqlTracker;

    public static function if(callable $shouldUseCondition): ?SqlProfiler
    {
        if ($shouldUseCondition() === false) {
            return null;
        }

        $profiler = new self();
        $profiler->registerShutdownFlush();
        $profiler->start();

        return $profiler;
    }

    public function start(): void
    {
        $this->sqlTracker = Application::getConnection()->startTracker(true);
    }

    public function stop(): void
    {
        Application::getConnection()->stopTracker();
    }

    public function flush(): void
    {
        try {
            if (Loader::includeModule('perfmon') === false) {
                return;
            }
        } catch (LoaderException $e) {
            return;
        }

        $queries = $this->sqlTracker->getQueries();
        $this->start();

        $hitId = $this->saveHit($queries);

        $perfKeeper = new CPerfomanceKeeper();

        $counter = 0;
        $perfKeeper->saveQueries($hitId, false, $queries, $counter);
    }

    /**
     * @psalm-param list<array{'TIME': float}> $queries
     *
     * @SuppressWarnings(PHPMD.CamelCaseVariableName) why:dependency
     * @SuppressWarnings(PHPMD.Superglobals) why:dependency
     */
    private function saveHit(array $queries): int
    {
        global $DB, $APPLICATION;

        $startMicrotime = microtime();

        $scriptName = in_array($_SERVER['SCRIPT_NAME'], ['/bitrix/urlrewrite.php', '/404.php'], true)
            && isset($_SERVER['REAL_FILE_PATH'])
            ? $_SERVER['REAL_FILE_PATH']
            : $_SERVER['SCRIPT_NAME'];

        $includeDebug = $APPLICATION->arIncludeDebug;

        $perfKeeper = new CPerfomanceKeeper();
        $queryCount = 0;
        $queryTime = 0.0;
        $perfKeeper->countQueries($queryCount, $queryTime, $queries, $includeDebug);

        $compsCount = 0;
        $compsTime = 0.0;
        $perfKeeper->countComponents($compsCount, $compsTime, $includeDebug);

        $hitFields = [
            '~DATE_HIT' => $DB->GetNowFunction(),
            'IS_ADMIN' => defined('ADMIN_SECTION') ? 'Y' : 'N',
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
            'SERVER_NAME' => $_SERVER['SERVER_NAME'],
            'SERVER_PORT' => $_SERVER['SERVER_PORT'],
            'SCRIPT_NAME' => $scriptName,
            'REQUEST_URI' => $_SERVER['REQUEST_URI'],
            'MEMORY_PEAK_USAGE' => memory_get_peak_usage(),
            'QUERIES' => $queryCount,
            '~QUERIES_TIME' => $queryTime,
            'SQL_LOG' => 'Y',
            'COMPONENTS' => $compsCount,
            '~COMPONENTS_TIME' => $compsTime,
            '~MENU_RECALC' => $APPLICATION->_menu_recalc_counter,
        ];

        CPerfomanceKeeper::SetPageTimes($startMicrotime, $hitFields);

        return $DB->Add('b_perf_hit', $hitFields);
    }

    public function registerShutdownFlush(): void
    {
        register_shutdown_function(function (): void {
            $this->flush();
        });
    }
}
