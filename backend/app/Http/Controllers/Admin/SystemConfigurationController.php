<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class SystemConfigurationController extends Controller
{
    public function index(): View
    {
        $extensions = get_loaded_extensions();
        natcasesort($extensions);
        $extensions = array_values($extensions);

        $requiredExtensions = [
            'curl',
            'json',
            'mbstring',
            'openssl',
            'pdo',
            'tokenizer',
            'xml',
            'ctype',
            'fileinfo',
            'filter',
        ];

        $missingRequired = array_values(array_filter(
            $requiredExtensions,
            static fn (string $name): bool => ! extension_loaded($name)
        ));

        return view('admin.system.index', [
            'phpVersion' => PHP_VERSION,
            'phpSapi' => PHP_SAPI,
            'phpOs' => PHP_OS_FAMILY.' ('.php_uname('s').' '.php_uname('r').')',
            'laravelVersion' => app()->version(),
            'timezone' => date_default_timezone_get(),
            'memoryLimit' => (string) ini_get('memory_limit'),
            'maxExecutionTime' => (string) ini_get('max_execution_time'),
            'uploadMaxFilesize' => (string) ini_get('upload_max_filesize'),
            'postMaxSize' => (string) ini_get('post_max_size'),
            'database' => $this->databaseInfo(),
            'extensions' => $extensions,
            'extensionCount' => count($extensions),
            'requiredExtensions' => $requiredExtensions,
            'missingRequired' => $missingRequired,
        ]);
    }

    /**
     * @return array{
     *   connection:string,
     *   driver:string,
     *   database:string,
     *   host:string,
     *   version:string,
     *   label:string,
     *   connected:bool,
     *   error:?string
     * }
     */
    private function databaseInfo(): array
    {
        $connection = (string) config('database.default', 'sqlite');
        $config = (array) config('database.connections.'.$connection, []);
        $driver = (string) ($config['driver'] ?? $connection);
        $database = (string) ($config['database'] ?? '');
        $host = (string) ($config['host'] ?? '');

        if ($driver === 'sqlite' && $database !== '') {
            $database = basename($database);
        }

        $info = [
            'connection' => $connection,
            'driver' => $driver,
            'database' => $database !== '' ? $database : '—',
            'host' => $host !== '' ? $host : '—',
            'version' => '—',
            'label' => $this->databaseLabel($driver),
            'connected' => false,
            'error' => null,
        ];

        try {
            $pdo = DB::connection($connection)->getPdo();
            $info['connected'] = true;
            $info['version'] = (string) ($pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) ?: '—');
        } catch (Throwable $e) {
            $info['error'] = $e->getMessage();
        }

        return $info;
    }

    private function databaseLabel(string $driver): string
    {
        return match (strtolower($driver)) {
            'sqlite' => 'SQLite',
            'mysql', 'mariadb' => 'MySQL / MariaDB',
            'pgsql', 'postgres', 'postgresql' => 'PostgreSQL',
            'sqlsrv' => 'SQL Server',
            default => strtoupper($driver),
        };
    }
}
