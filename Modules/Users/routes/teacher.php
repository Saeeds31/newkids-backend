<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\TeacherController;

Route::prefix('teachers')->controller(TeacherController::class)->group(function () {
    Route::get('/', 'index')->name('teachers.index');
    Route::post('/', 'store')->name('teachers.store');
    Route::get('/statistics', 'statistics')->name('teachers.statistics');
    Route::get('/{id}', 'show')->name('teachers.show');
    Route::put('/{id}', 'update')->name('teachers.update');
    Route::delete('/{id}', 'destroy')->name('teachers.destroy');
    Route::post('/{id}/activate', 'activate')->name('teachers.activate');
    Route::get('/{id}/students', 'getStudents')->name('teachers.students');
    Route::get('/{id}/classes', 'getClasses')->name('teachers.classes');
    Route::get('/{id}/tasks', 'getTasks')->name('teachers.tasks');
});
