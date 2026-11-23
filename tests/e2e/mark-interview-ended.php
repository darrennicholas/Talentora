<?php

use App\Models\Interview;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'talentora_test') {
    fwrite(STDERR, "Refusing to update an interview outside the E2E test database.\n");
    exit(2);
}

$applicationId = (int) ($argv[1] ?? 0);
$interview = Interview::query()->where('application_id', $applicationId)->first();

if (! $interview) {
    fwrite(STDERR, "No interview exists for the requested test application.\n");
    exit(1);
}

$interview->update([
    'start_at' => now()->subMinutes(2),
    'end_at' => now()->subMinute(),
]);
