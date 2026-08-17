<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'admin@emploijeunes.ci')->first();
if ($user) {
    echo "User ID: " . $user->id . "\n";
    \Illuminate\Support\Facades\Auth::login($user);
}

$query = \App\Models\Workflow\InstanceParcours::query();
if ($user && $user->agence_id) {
    $query->whereHas('stage', function ($q) use ($user) {
        $q->where('agence_id', $user->agence_id);
    });
}
echo "Count: " . $query->count() . "\n";
