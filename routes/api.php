<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\CoupleController;
use App\Http\Controllers\PlantingActivityController;
use App\Http\Controllers\TreePlanterController;
use App\Http\Controllers\TreeController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ProfileController;

// API Routes for authentication (no CSRF required)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/validate-code', [AuthController::class, 'validateCode']);
Route::get('/organizations/by-code/{code}', [OrganizationController::class, 'getByCode']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // User Profile
    Route::prefix('user')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);
    });

    // =========================
    // PHASE 1: Organization & Couple Management (Admin)
    // =========================
    // Organizations
    Route::prefix('organizations')->group(function () {
        Route::get('/', [OrganizationController::class, 'index']);
        Route::post('/', [OrganizationController::class, 'store']);
        Route::get('/{id}', [OrganizationController::class, 'show']);
        Route::put('/{id}', [OrganizationController::class, 'update']);
        Route::delete('/{id}', [OrganizationController::class, 'destroy']);
        Route::get('/{id}/users', [OrganizationController::class, 'getUsers']);
    });

    // Couples Management (Admin)
    Route::prefix('couples')->group(function () {
        Route::get('/', [CoupleController::class, 'index']);
        Route::post('/', [CoupleController::class, 'store']);
        Route::get('/{id}', [CoupleController::class, 'show']);
        Route::put('/{id}', [CoupleController::class, 'update']);
        Route::delete('/{id}', [CoupleController::class, 'destroy']);
        Route::get('/{id}/users', [CoupleController::class, 'getUsers']);
    });

    // Tree Planters Management (Admin)
    Route::prefix('tree-planters')->group(function () {
        Route::get('/', [TreePlanterController::class, 'index']);
        Route::post('/', [TreePlanterController::class, 'store']);
        Route::get('/{id}', [TreePlanterController::class, 'show']);
        Route::put('/{id}', [TreePlanterController::class, 'update']);
        Route::delete('/{id}', [TreePlanterController::class, 'destroy']);
    });

    // Planting Activities
    Route::prefix('planting-activities')->group(function () {
        Route::get('/', [PlantingActivityController::class, 'index']);
        Route::post('/', [PlantingActivityController::class, 'store']);
        Route::get('/{id}', [PlantingActivityController::class, 'show']);
        Route::put('/{id}', [PlantingActivityController::class, 'update']);
        Route::delete('/{id}', [PlantingActivityController::class, 'destroy']);
    });

    // =========================
    // PHASE 2: Tree Planting & Registration (Tree Planters)
    // =========================
    // Trees
    Route::prefix('trees')->group(function () {
        Route::get('/', [TreeController::class, 'index']);
        Route::post('/', [TreeController::class, 'store']);
        Route::get('/my-trees', [TreeController::class, 'myTrees']);
        Route::get('/by-activity/{activityId}', [TreeController::class, 'byActivity']);
        Route::get('/{id}', [TreeController::class, 'show']);
        Route::post('/sync', [TreeController::class, 'sync']);
    });

    // =========================
    // PHASE 3: Monitoring (Monitoring Staff)
    // =========================
    // Monitoring
    Route::prefix('monitoring')->group(function () {
        Route::get('/assignments', [MonitoringController::class, 'assignments']);
        Route::get('/trees-for-monitoring', [MonitoringController::class, 'getTreesForMonitoring']);
        Route::post('/', [MonitoringController::class, 'store']);
        Route::get('/history', [MonitoringController::class, 'history']);
        Route::post('/sync', [MonitoringController::class, 'sync']);
    });

    // Attendance Records
    Route::prefix('attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/', [AttendanceController::class, 'store']);
        Route::get('/summary', [AttendanceController::class, 'summary']);
        Route::get('/{id}', [AttendanceController::class, 'show']);
        Route::put('/{id}', [AttendanceController::class, 'update']);
        Route::delete('/{id}', [AttendanceController::class, 'destroy']);
    });

    // =========================
    // AUTHENTICATED ROUTES
    // =========================
    Route::get('/user/{id}/organizations', function ($id) {
        return \App\Models\UserOrganization::where('user_id', $id)->get();
    });
    Route::get('/calendar-events', [DashboardController::class, 'calendarEvents']);
});
