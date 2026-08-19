<?php

use App\Models\User;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::where('email', 'admin@emploijeunes.ci')->first();
if ($user) {
    echo 'User ID: '.$user->id."\n";
    Auth::login($user);
}

$query = InstanceParcours::query();
if ($user && $user->agence_id) {
    $query->whereHas('stage', function ($q) use ($user) {
        $q->where('agence_id', $user->agence_id);
    });
}
echo 'Count: '.$query->count()."\n";
