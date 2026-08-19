<?php

use App\Models\User;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = User::where('email', 'admin@emploijeunes.ci')->first();
Auth::login($user);

$filters = []; // simulate empty filters
$request = new Request($filters);
$request->headers->set('Accept', 'application/json');

$query = InstanceParcours::with([
    'stage.beneficiaire.typePaiement',
    'stage.entreprise.typeStructure',
    'stage.agence',
    'stage.sourceFinancement',
    'stage.typeStage',
    'stage.contrats',
    'stage.pointages.periode',
    'stage.pointages.versionCourante',
]);

if ($user && $user->agence_id) {
    $query->whereHas('stage', function ($q) use ($user) {
        $q->where('agence_id', $user->agence_id);
    });
}

$instances = $query->orderBy('created_at', 'desc')->paginate(50);
$json = json_encode(['instances' => $instances]);

if ($json === false) {
    echo 'JSON Encode failed: '.json_last_error_msg()."\n";
} else {
    echo 'JSON String Length: '.strlen($json)."\n";
    if (strlen($json) > 100) {
        echo 'JSON snippet: '.substr($json, 0, 100)."...\n";
    }
}
