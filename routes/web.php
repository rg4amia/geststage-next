<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Tables/GridJs/index')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Tables/GridJs/index')->name('dashboard');
});

require __DIR__.'/settings.php';
