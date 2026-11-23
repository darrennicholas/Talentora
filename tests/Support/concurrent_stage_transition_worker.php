<?php

use App\Models\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

[$script, $applicationId, $userId, $toStage, $note, $gate, $ready] = $argv;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$app->instance('request', Request::create('/'));
Auth::forgetGuards();
Auth::guard('web')->loginUsingId((int) $userId);
file_put_contents($ready, 'ready');

$deadline = microtime(true) + 30;
while (! file_exists($gate) && microtime(true) < $deadline) {
    usleep(5_000);
}

if (! file_exists($gate)) {
    fwrite(STDERR, 'Timed out waiting for transition gate.');
    exit(2);
}

$payload = [
    'from_stage' => 'applied',
    'to_stage' => $toStage,
    'note' => $note ?: null,
];
$request = Request::create(
    '/api/applications/'.(int) $applicationId.'/stage',
    'PATCH',
    [],
    [],
    [],
    ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
    json_encode($payload, JSON_THROW_ON_ERROR),
);
$response = $kernel->handle($request);
echo $response->getStatusCode();
$kernel->terminate($request, $response);
