<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetCreateController;
use App\Http\Controllers\BudgetDocumentApiController;
use App\Http\Controllers\BudgetListController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

// Guest / Authentication routes
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');

// Authenticated Budget routes
Route::middleware('auth')->group(function () {
    // Budget List
    Route::get('/', [BudgetListController::class, 'index'])->name('budget_list');
    Route::get('/budget_list', [BudgetListController::class, 'index']);

    // Budget Create & Mutation routes (protected by not_frozen middleware)
    Route::middleware('not_frozen')->group(function () {
        Route::match(['get', 'post'], '/add', [BudgetCreateController::class, 'vetManpower'])->name('budget_add');
        Route::match(['get', 'post'], '/add_non_vet', [BudgetCreateController::class, 'nonVetManpower'])->name('budget_add_non_vet');
        Route::match(['get', 'post'], '/add_adjustment', [BudgetCreateController::class, 'positionAdjustment'])->name('budget_add_adjustment');
        Route::match(['get', 'post'], '/add_medical_equipment', [BudgetCreateController::class, 'medicalEquipment'])->name('budget_add_medical_equipment');
        Route::match(['get', 'post'], '/add_computer_equipment', [BudgetCreateController::class, 'computerEquipment'])->name('budget_add_computer_equipment');
        Route::match(['get', 'post'], '/add_furniture', [BudgetCreateController::class, 'furniture'])->name('budget_add_furniture');
        Route::match(['get', 'post'], '/add_tools_equipment', [BudgetCreateController::class, 'toolsEquipment'])->name('budget_add_tools_equipment');
        Route::match(['get', 'post'], '/add_car', [BudgetCreateController::class, 'car'])->name('budget_add_car');
        Route::match(['get', 'post'], '/add_renovate', [BudgetCreateController::class, 'renovate'])->name('budget_add_renovate');
        Route::match(['get', 'post'], '/add_software', [BudgetCreateController::class, 'software'])->name('budget_add_software');
        Route::match(['get', 'post'], '/add_gl_entry', [BudgetCreateController::class, 'glEntry'])->name('budget_add_gl_entry');
        Route::match(['get', 'post'], '/add_revenue', [BudgetCreateController::class, 'revenue'])->name('budget_add_revenue');
        Route::match(['get', 'post'], '/add_budget_plan', [BudgetCreateController::class, 'budgetPlan'])->name('budget_add_budget_plan');
        Route::match(['get', 'post'], '/add_gl_actual_template', [BudgetCreateController::class, 'glActualTemplate'])->name('budget_add_gl_actual_template');

        Route::post('/api/document/update/{doc_type}/{doc_no}', [BudgetDocumentApiController::class, 'updateDocument'])->name('api_document_update');
        Route::post('/api/document/approve/{doc_type}/{doc_no}', [BudgetDocumentApiController::class, 'approveDocument'])->name('api_document_approve');
        Route::post('/api/document/cancel/{doc_type}/{doc_no}', [BudgetDocumentApiController::class, 'cancelDocument'])->name('api_document_cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Dashboard Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('budget_dashboard');
    Route::get('/api/dashboard/data', [DashboardController::class, 'data'])->name('api_dashboard_data');
    Route::get('/api/dashboard/export', [DashboardController::class, 'export'])->name('api_dashboard_export');

    // Budget Document APIs
    Route::get('/api/get_documents/{category_code}', [BudgetDocumentApiController::class, 'getDocuments'])->name('api_get_documents');
    Route::get('/api/document/{doc_type}/{doc_no}', [BudgetDocumentApiController::class, 'getDocumentDetail'])->name('api_document_detail');
    Route::get('/api/general_ledgers', [BudgetDocumentApiController::class, 'getGeneralLedgers'])->name('api_general_ledgers');
    Route::get('/api/check_duplicate_document', [BudgetDocumentApiController::class, 'checkDuplicate'])->name('api_check_duplicate_document');
    Route::match(['get', 'post'], '/api/session/keepalive', [BudgetDocumentApiController::class, 'keepalive'])->name('api_session_keepalive');

    // System Control APIs
    Route::post('/system/toggle_freeze', [SystemController::class, 'toggleFreeze'])->name('toggle_freeze');
    Route::post('/system/set_active_year', [SystemController::class, 'setActiveYear'])->name('set_active_year');
});
