<?php

use App\Http\Controllers\ParametreAides\AgenceController;
use App\Http\Controllers\ParametreAides\AideController;
use App\Http\Controllers\ParametreAides\ConseillerController;
use App\Http\Controllers\ParametreAides\JournalAuditController;
use App\Http\Controllers\ParametreAides\ParametreAidesController;
use App\Http\Controllers\ParametreAides\ParametreSystemeController;
use App\Http\Controllers\ParametreAides\PrimeController;
use App\Http\Controllers\ParametreAides\UtilisateurController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Paramètre & Aides
|--------------------------------------------------------------------------
|
| Centre d'administration : comptes, référentiels d'organisation, paramètres
| de calcul, traçabilité et guide utilisateur. Les entreprises et les offres
| conservent leurs routes historiques (`/entreprises`, `/offres`) : le hub s'y
| raccroche plutôt que de dupliquer les modules.
|
| L'autorisation fine est portée par les contrôleurs et les FormRequest ; le
| `can:` posé ici sert de premier filtre de navigation.
*/

Route::middleware(['auth', 'verified'])->prefix('parametre-aides')->name('parametre-aides.')->group(function (): void {
    Route::get('/', [ParametreAidesController::class, 'index'])->name('index');

    Route::get('/aide', [AideController::class, 'index'])->name('aide.index');

    // Comptes utilisateurs (legacy `/comptes`)
    Route::prefix('comptes')->name('comptes.')->group(function (): void {
        Route::get('/', [UtilisateurController::class, 'index'])->middleware('can:voir_utilisateurs')->name('index');
        Route::get('/creer', [UtilisateurController::class, 'create'])->middleware('can:gerer_utilisateurs')->name('create');
        Route::post('/', [UtilisateurController::class, 'store'])->middleware('can:gerer_utilisateurs')->name('store');
        Route::get('/{utilisateur}/modifier', [UtilisateurController::class, 'edit'])->middleware('can:gerer_utilisateurs')->name('edit');
        Route::put('/{utilisateur}', [UtilisateurController::class, 'update'])->middleware('can:gerer_utilisateurs')->name('update');
        Route::post('/{utilisateur}/activation', [UtilisateurController::class, 'basculerActivation'])->middleware('can:gerer_utilisateurs')->name('activation');
        Route::post('/{utilisateur}/usurper', [UtilisateurController::class, 'usurper'])->middleware('can:usurper_identite')->name('usurper');
    });

    // Conseillers (legacy `/conseiller`)
    Route::prefix('conseillers')->name('conseillers.')->middleware('can:voir_referentiels')->group(function (): void {
        Route::get('/', [ConseillerController::class, 'index'])->name('index');
        Route::get('/creer', [ConseillerController::class, 'create'])->middleware('can:gerer_referentiels')->name('create');
        Route::post('/', [ConseillerController::class, 'store'])->middleware('can:gerer_referentiels')->name('store');
        Route::get('/{conseiller}/modifier', [ConseillerController::class, 'edit'])->middleware('can:gerer_referentiels')->name('edit');
        Route::put('/{conseiller}', [ConseillerController::class, 'update'])->middleware('can:gerer_referentiels')->name('update');
        Route::post('/{conseiller}/compte', [ConseillerController::class, 'rattacherCompte'])->middleware('can:gerer_utilisateurs')->name('compte');
    });

    // Agences (legacy `/Agences`) — édition réservée à l'administrateur.
    Route::prefix('agences')->name('agences.')->middleware('can:voir_referentiels')->group(function (): void {
        Route::get('/', [AgenceController::class, 'index'])->name('index');
        Route::get('/creer', [AgenceController::class, 'create'])->middleware('can:gerer_agences')->name('create');
        Route::post('/', [AgenceController::class, 'store'])->middleware('can:gerer_agences')->name('store');
        Route::get('/{agence}/modifier', [AgenceController::class, 'edit'])->middleware('can:gerer_agences')->name('edit');
        Route::put('/{agence}', [AgenceController::class, 'update'])->middleware('can:gerer_agences')->name('update');
    });

    // Paramètres système (legacy `/settings/systeme/general`)
    Route::prefix('parametres-systeme')->name('parametres-systeme.')->middleware('can:voir_parametres_systeme')->group(function (): void {
        Route::get('/', [ParametreSystemeController::class, 'index'])->name('index');
        Route::post('/general', [ParametreSystemeController::class, 'updateGeneral'])->middleware('can:gerer_parametres_systeme')->name('general.update');
        Route::post('/prelevements', [ParametreSystemeController::class, 'storeRegle'])->middleware('can:gerer_parametres_systeme')->name('prelevements.store');
        Route::put('/prelevements/{regle}', [ParametreSystemeController::class, 'updateRegle'])->middleware('can:gerer_parametres_systeme')->name('prelevements.update');
        Route::delete('/prelevements/{regle}', [ParametreSystemeController::class, 'destroyRegle'])->middleware('can:gerer_parametres_systeme')->name('prelevements.destroy');
    });

    // Barème des primes (legacy `/settings/primes`)
    Route::prefix('primes')->name('primes.')->middleware('can:voir_parametres_systeme')->group(function (): void {
        Route::get('/', [PrimeController::class, 'index'])->name('index');
        Route::put('/', [PrimeController::class, 'update'])->middleware('can:gerer_parametres_systeme')->name('update');
        Route::post('/reinitialiser', [PrimeController::class, 'reset'])->middleware('can:gerer_parametres_systeme')->name('reset');
        Route::post('/simuler', [PrimeController::class, 'simuler'])->name('simuler');
    });

    // Journaux d'activité (legacy `/settings/activity-logs`)
    Route::prefix('journaux')->name('journaux.')->middleware('can:voir_journaux_audit')->group(function (): void {
        Route::get('/', [JournalAuditController::class, 'index'])->name('index');
        Route::get('/export', [JournalAuditController::class, 'export'])->name('export');
    });
});

// Sortie d'usurpation : accessible au compte usurpé, qui n'a par définition
// aucune des permissions d'administration ci-dessus.
Route::middleware(['auth'])
    ->post('/parametre-aides/cesser-usurpation', [UtilisateurController::class, 'cesserUsurpation'])
    ->name('parametre-aides.cesser-usurpation');
