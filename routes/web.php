<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Tables/GridJs/index')->name('dashboard');

    Route::resource('entreprises', \App\Http\Controllers\Company\EntrepriseController::class);
    Route::resource('offres', \App\Http\Controllers\Company\OffreEmploiController::class)->parameters([
        'offres' => 'offre_emploi'
    ]);
});

require __DIR__.'/settings.php';
