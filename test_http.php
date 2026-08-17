<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = \App\Models\User::where('email', 'admin@emploijeunes.ci')->first();
\Illuminate\Support\Facades\Auth::login($user);

$request = \Illuminate\Http\Request::create('/cip/mes-stagiaires', 'GET');
$request->headers->set('Accept', 'application/json');

$response = $kernel->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content snippet: " . substr($response->getContent(), 0, 150) . "\n";
