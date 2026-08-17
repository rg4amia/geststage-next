<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'admin@emploijeunes.ci')->first();
\Illuminate\Support\Facades\Auth::login($user);

$filters = []; // simulate empty filters
$request = new \Illuminate\Http\Request($filters);
$request->headers->set('Accept', 'application/json');

$query = \App\Models\Workflow\InstanceParcours::with([
    'stage.beneficiaire.typePaiement',
    'stage.entreprise.typeStructure',
    'stage.agence',
    'stage.sourceFinancement',
    'stage.typeStage',
    'stage.contrats',
    'stage.pointages.periode',
    'stage.pointages.versionCourante'
]);

if ($user && $user->agence_id) {
    $query->whereHas('stage', function ($q) use ($user) {
        $q->where('agence_id', $user->agence_id);
    });
}

$instances = $query->orderBy('created_at', 'desc')->paginate(50);
$json = json_encode(['instances' => $instances]);

if ($json === false) {
    echo "JSON Encode failed: " . json_last_error_msg() . "\n";
} else {
    echo "JSON String Length: " . strlen($json) . "\n";
    if (strlen($json) > 100) {
        echo "JSON snippet: " . substr($json, 0, 100) . "...\n";
    }
}
