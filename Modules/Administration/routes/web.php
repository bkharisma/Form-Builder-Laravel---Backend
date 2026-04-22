<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Administration' => 'Module loaded'];
});
