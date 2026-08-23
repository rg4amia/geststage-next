<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Internship\Stage;
use App\Models\Auth\User;
use App\Domain\Validation\Services\ValidationChefAgenceService;
use Illuminate\Support\Facades\DB;

$stages = Stage::whereYear('date_debut', 2026)->whereMonth('date_debut', 8)
    ->whereHas('instanceParcours', fn($q) => $q->where('corbeille_actuelle', 'ca_attente_validation_demarrage'))
    ->take(10)->get();

$service = app(ValidationChefAgenceService::class);
$user = App\Models\User::first();

foreach ($stages as $stage) {
    try {
        $service->validerDemarrage($stage->instanceParcours, $user);
        echo "Success " . $stage->id . "\n";
    } catch (\Exception $e) {
        echo "Failed " . $stage->id . ": " . $e->getMessage() . "\n";
    }
}
