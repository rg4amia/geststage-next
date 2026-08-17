<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/cip/mes-stagiaires', 'GET');
$request->headers->set('Accept', 'application/json');

$user = \App\Models\User::where('email', 'admin@emploijeunes.ci')->first();
$app->make('auth')->guard()->login($user);

$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $httpKernel->handle($request);
$content = json_decode($response->getContent(), true);
echo "Instances type: " . gettype($content['instances']) . "\n";
echo "Instances keys: " . implode(', ', array_keys($content['instances'])) . "\n";
echo "Data type: " . gettype($content['instances']['data']) . "\n";
echo "Data length: " . count($content['instances']['data']) . "\n";
