<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\ConsultationWorkflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispensationController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\DocumentWorkflowController;
use App\Http\Controllers\MedicalDocumentController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::get('/verify/{token}', DocumentVerificationController::class)->name('documents.verify');

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::resource('patients', PatientController::class)
        ->middlewareFor(['index', 'show'], 'permission:patients.view')
        ->middlewareFor(['create', 'store'], 'permission:patients.create')
        ->middlewareFor(['edit', 'update'], 'permission:patients.update')
        ->middlewareFor('destroy', 'permission:patients.archive');
    Route::post('/patients/{patient}/allergies', [PatientController::class, 'storeAllergy'])
        ->middleware(['permission:patients.update', 'permission:clinical-data.view'])->name('patients.allergies.store');
    Route::delete('/patients/{patient}/allergies/{allergy}', [PatientController::class, 'deactivateAllergy'])
        ->middleware(['permission:patients.update', 'permission:clinical-data.view'])->name('patients.allergies.destroy');

    Route::resource('appointments', AppointmentController::class)
        ->middlewareFor(['index', 'show'], 'permission:appointments.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:appointments.manage');

    Route::resource('consultations', ConsultationController::class)
        ->middlewareFor(['index', 'show'], 'permission:consultations.view')
        ->middlewareFor(['create', 'store'], 'permission:consultations.create')
        ->middlewareFor(['edit', 'update', 'destroy'], 'permission:consultations.update');
    Route::post('/consultations/{consultation}/finalize', [ConsultationWorkflowController::class, 'finalize'])
        ->middleware('permission:consultations.finalize')->name('consultations.finalize');
    Route::post('/consultations/{consultation}/addenda', [ConsultationWorkflowController::class, 'addendum'])
        ->middleware('permission:consultations.addendum')->name('consultations.addenda.store');
    Route::post('/consultations/{consultation}/exams/{examRequest}/result', [ConsultationWorkflowController::class, 'recordExamResult'])
        ->middleware('permission:consultations.addendum')->name('consultations.exams.result');

    Route::resource('documents', MedicalDocumentController::class)
        ->middlewareFor(['index', 'show'], 'permission:documents.view')
        ->middlewareFor(['create', 'store'], 'permission:documents.create')
        ->middlewareFor(['edit', 'update'], 'permission:documents.update')
        ->middlewareFor('destroy', 'permission:documents.delete');
    Route::get('/documents/{document}/download', [MedicalDocumentController::class, 'download'])
        ->middleware('permission:documents.download')->name('documents.download');
    Route::post('/documents/{document}/submit', [DocumentWorkflowController::class, 'submit'])
        ->middleware('permission:documents.submit')->name('documents.submit');
    Route::post('/documents/{document}/approve', [DocumentWorkflowController::class, 'approve'])
        ->middleware('permission:documents.approve')->name('documents.approve');
    Route::post('/documents/{document}/reject', [DocumentWorkflowController::class, 'reject'])
        ->middleware('permission:documents.reject')->name('documents.reject');
    Route::post('/documents/{document}/reopen', [DocumentWorkflowController::class, 'reopen'])
        ->middleware('permission:documents.update')->name('documents.reopen');
    Route::post('/documents/{document}/revoke', [DocumentWorkflowController::class, 'revoke'])
        ->middleware('permission:documents.revoke')->name('documents.revoke');

    Route::resource('products', ProductController::class)->except('show')
        ->middlewareFor('index', 'permission:pharmacy.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:products.manage');
    Route::get('/stock', [StockController::class, 'index'])->middleware('permission:pharmacy.view')->name('stock.index');
    Route::post('/stock/receive', [StockController::class, 'receive'])->middleware('permission:stock.receive')->name('stock.receive');
    Route::post('/stock/adjust', [StockController::class, 'adjust'])->middleware('permission:stock.receive')->name('stock.adjust');
    Route::post('/stock/transfer', [StockController::class, 'transfer'])->middleware('permission:stock.receive')->name('stock.transfer');
    Route::resource('dispensations', DispensationController::class)->only(['index', 'create', 'store', 'show'])
        ->middleware('permission:pharmacy.dispense');
    Route::post('/dispensations/{dispensation}/items/{item}/return', [DispensationController::class, 'returnItem'])
        ->middleware('permission:pharmacy.dispense')->name('dispensations.items.return');

    Route::get('/reports', ReportController::class)->middleware('permission:reports.view')->name('reports.index');

    Route::get('/administration', [AdministrationController::class, 'index'])
        ->middleware('permission:administration.manage')->name('administration.index');
    Route::post('/administration/sites', [AdministrationController::class, 'storeSite'])
        ->middleware('permission:administration.manage')->name('administration.sites.store');
    Route::post('/administration/units', [AdministrationController::class, 'storeUnit'])
        ->middleware('permission:administration.manage')->name('administration.units.store');
    Route::post('/administration/users', [AdministrationController::class, 'storeUser'])
        ->middleware('permission:administration.manage')->name('administration.users.store');
    Route::put('/administration/users/{user}', [AdministrationController::class, 'updateUser'])
        ->middleware('permission:administration.manage')->name('administration.users.update');
});

require __DIR__.'/auth.php';
