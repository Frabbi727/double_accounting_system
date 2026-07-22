<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Toggle the UI language (bn/en). The chosen locale is remembered in the
// session and applied by the SetLocale middleware on every request.
Route::get('/locale/{locale}', function (string $locale) {
    if (in_array($locale, ['bn', 'en'], true)) {
        session(['locale' => $locale]);
    }

    return back();
})->name('locale.switch');
