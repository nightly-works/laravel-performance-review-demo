<?php

/*
 * Measures the back office's heavy pages on a seeded shop (SHOP_ORDERS orders, 20,000 by default) and prints
 * one Markdown row per page: SQL queries, Eloquent models loaded, median time of three runs and peak memory.
 *
 *   php scripts/measure.php [database file]
 *
 * The database file (default: a fresh file under storage/) is rebuilt with migrate:fresh and ShopSeeder.
 * Mail goes to the array driver, so sending time is not counted: the report covers it separately.
 */

$root = dirname(__DIR__);
$db = $argv[1] ?? $root.'/storage/measure.sqlite';
@unlink($db);
touch($db);

foreach (['APP_ENV' => 'local', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $db, 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'APP_DEBUG' => 'false'] as $k => $v) {
    putenv("$k=$v");
    $_ENV[$k] = $_SERVER[$k] = $v;
}

require $root.'/vendor/autoload.php';

function boot(string $root): Illuminate\Foundation\Application
{
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    return $app;
}

$app = boot($root);
Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => Database\Seeders\ShopSeeder::class, '--force' => true]);
Illuminate\Support\Facades\DB::table('users')->insert(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);

$pages = [
    'Orders to ship (`/orders`)' => '/orders',
    'Shipped orders, page 100 (`/orders?status=shipped&page=100`)' => '/orders?status=shipped&page=100',
    'Revenue report (`/reports/revenue`)' => '/reports/revenue',
    'CSV export (`/orders/export`)' => '/orders/export',
];

echo "| Page | Queries | Models loaded | Time (median of 3) | Peak memory |\n|---|---:|---:|---:|---:|\n";

foreach ($pages as $label => $uri) {
    $times = [];
    for ($run = 0; $run < 3; $run++) {
        $app = boot($root);
        $queries = 0;
        $models = 0;
        $app['db']->listen(function () use (&$queries) { $queries++; });
        $app['events']->listen('eloquent.retrieved: *', function () use (&$models) { $models++; });
        $app['auth']->guard('web')->setUser(App\Models\User::first());
        $queries = $models = 0;

        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        gc_collect_cycles();
        $base = memory_get_usage();
        memory_reset_peak_usage();
        $start = hrtime(true);

        $response = $kernel->handle(Illuminate\Http\Request::create($uri));
        // Send the body (a streamed response does its work here), counting bytes without keeping them.
        $bytes = 0;
        ob_start(function (string $chunk) use (&$bytes) { $bytes += strlen($chunk); return ''; }, 8192);
        $response->sendContent();
        ob_end_clean();

        $times[] = (hrtime(true) - $start) / 1e6;
        $peak = memory_get_peak_usage() - $base;
        if ($response->getStatusCode() !== 200) {
            fwrite(STDERR, "$uri answered {$response->getStatusCode()}\n");
            exit(1);
        }
        unset($response, $kernel, $app);
    }
    sort($times);
    printf("| %s | %d | %s | %d ms | %.1f MB |\n", $label, $queries, number_format($models), $times[1], $peak / 1048576);
}

@unlink($db);
