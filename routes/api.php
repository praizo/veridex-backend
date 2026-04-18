<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\NrsResourceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\NrsWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Routes (Throttled)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/onboarding/verify-tin', [OnboardingController::class, 'verifyTin']);
    Route::post('/webhooks/nrs', [NrsWebhookController::class, 'handle']);
});

// Protected Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Organization-Scoped Routes
    Route::middleware('org.scope')->group(function () {
        
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/health', [DashboardController::class, 'health']);

        // Governance & Reports
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/reports/invoices/csv', [ReportController::class, 'exportCsv']);
        Route::get('/reports/b2c/summary', [ReportController::class, 'b2cSummary']);

        // Organizations & Team
        Route::apiResource('organizations', OrganizationController::class)->only(['index', 'show', 'update']);
        Route::post('organizations/switch', [OrganizationController::class, 'switch']);
        Route::get('organization/current', [OrganizationController::class, 'show']); // Shared alias
        
        Route::apiResource('team/members', TeamController::class)->except(['show']);

        // FIRS Resources
        Route::prefix('resources')->group(function () {
            Route::get('hs-codes', [NrsResourceController::class, 'hsCodes']);
            Route::get('currencies', [NrsResourceController::class, 'currencies']);
            Route::get('tax-categories', [NrsResourceController::class, 'taxCategories']);
            Route::get('invoice-types', [NrsResourceController::class, 'invoiceTypes']);
            Route::get('payment-means', [NrsResourceController::class, 'paymentMeans']);
            Route::get('service-codes', [NrsResourceController::class, 'serviceCodes']);
            Route::get('vat-exemptions', [NrsResourceController::class, 'vatExemptions']);
        });

        // Invoices
        Route::apiResource('invoices', InvoiceController::class);
        Route::prefix('invoices/{invoice}')->group(function () {
            Route::post('validate', [InvoiceController::class, 'validateOnNrs']);
            Route::post('sign', [InvoiceController::class, 'signOnNrs']);
            Route::post('transmit', [InvoiceController::class, 'transmitOnNrs']);
            Route::post('confirm', [InvoiceController::class, 'confirmOnNrs']);
            
            // Phase 5 Additions
            Route::get('download', [InvoiceController::class, 'downloadPdf']);
            Route::patch('payment', [InvoiceController::class, 'updatePaymentStatus']);
        });

        // Customers
        Route::apiResource('customers', CustomerController::class);

        // Products
        Route::apiResource('products', ProductController::class);
    });
});