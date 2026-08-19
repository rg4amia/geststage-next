<?php

use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);

$user = User::where('email', 'admin@emploijeunes.ci')->first();
Auth::login($user);

$request = Request::create('/cip/mes-stagiaires', 'GET');
$request->headers->set('Accept', 'application/json');

$response = $kernel->handle($request);
echo substr($response->getContent(), 0, 200);
