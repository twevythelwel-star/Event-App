<?php

declare(strict_types=1);

// Run with a production-configured Laravel bootstrap to emit a non-secret backup manifest.
require dirname(__DIR__) . '/backend/vendor/autoload.php';
$app = require dirname(__DIR__) . '/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!app()->environment('production')) {
    fwrite(STDERR, "Refusing to generate a production manifest outside APP_ENV=production.\n");
    exit(2);
}

$tables = ['events', 'orders', 'order_items', 'order_adjustments', 'cash_payments', 'payments', 'security_audit_logs', 'handover_audit_logs'];
$counts = [];
foreach ($tables as $table) {
    if (Illuminate\Support\Facades\Schema::hasTable($table)) {
        $counts[$table] = Illuminate\Support\Facades\DB::table($table)->count();
    }
}

echo json_encode(['created_at' => now()->toIso8601String(), 'database' => config('database.default'), 'counts' => $counts], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
