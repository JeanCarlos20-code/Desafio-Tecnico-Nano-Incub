<?php

declare(strict_types=1);

use App\Modules\User\Infra\Database\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;

$basePath = dirname(__DIR__, 4);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$roomId = (string) $argv[1];
$userId = (string) $argv[2];
$barrier = (string) $argv[3];
$startsAt = (string) $argv[4];
$endsAt = (string) $argv[5];
$workerId = (string) $argv[6];

file_put_contents($barrier.'.ready.'.$workerId, '1');

$deadline = microtime(true) + 15;
while (! is_file($barrier) || trim((string) file_get_contents($barrier)) !== 'go') {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "timeout waiting for go\n");
        exit(2);
    }

    usleep(5000);
}

$lifecycleDeadline = microtime(true) + 15;
while (! is_file($barrier.'.lifecycle-started')) {
    if (microtime(true) > $lifecycleDeadline) {
        fwrite(STDERR, "timeout waiting for lifecycle\n");
        exit(2);
    }

    usleep(5000);
}

$app->instance('middleware.disable', true);
Auth::guard('web')->setUser(User::query()->findOrFail($userId));

$request = Request::create('/reservations', 'POST', [
    'room_id' => $roomId,
    'responsible' => 'Ada '.$workerId,
    'title' => 'After lifecycle '.$workerId,
    'starts_at' => $startsAt,
    'ends_at' => $endsAt,
    'participants' => 2,
]);
$request->headers->set('Accept', 'text/html');
$request->headers->set('Referer', '/reservations/create');
$session = $app->make('session.store');
$session->start();
$app->instance('session.store', $session);
$request->setLaravelSession($session);
file_put_contents($barrier.'.create-started', '1');

$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);
echo (string) $response->getStatusCode();
$errors = $request->session()->get('errors');
if ($errors instanceof ViewErrorBag) {
    echo "\n".$errors->first('room_id');
}
$kernel->terminate($request, $response);
