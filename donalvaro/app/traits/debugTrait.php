<?php

declare(strict_types=1);

/**
 * @author Sandra Campos
 * @copyright Wilowi
 * @since 2 jul 2026
 */

trait debugTrait
{
    protected function printDebug(
        mixed $value,
        bool $isCli = false,
        bool $useVarDump = false
    ): void {

        ini_set('display_errors', _APP_DEBUG ? '1' : '0');

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        $location = isset($trace['file'], $trace['line'])
            ? basename($trace['file']) . ' (line ' . $trace['line'] . ')'
            : 'Ubicación desconocida';

        if ($isCli) {

            echo PHP_EOL;
            echo "========== DEBUG ==========" . PHP_EOL;
            echo $location . PHP_EOL . PHP_EOL;

            $useVarDump ? var_dump($value) : print_r($value);

            echo "===========================" . PHP_EOL;

            return;
        }

        echo '<div style="
                background:#f8fafc;
                border-left:4px solid #0f766e;
                padding:12px;
                margin:15px 0;
                font-family:monospace;
                white-space:pre-wrap;">';

        echo '<strong>' . htmlspecialchars($location) . '</strong><br><br>';

        ob_start();

        $useVarDump
            ? var_dump($value)
            : print_r($value);

        echo htmlspecialchars(ob_get_clean());

        echo '</div>';
    }
}
