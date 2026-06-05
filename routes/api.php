<?php
use App\Http\Controllers\ApiController;
use App\Http\Controllers\OperationsApiController;
use App\Http\Controllers\AdminApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [ApiController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware(['auth:api', 'throttle:120,1'])->group(function () {
        Route::get('/auth/me', [ApiController::class, 'me']);
        Route::post('/auth/logout', [ApiController::class, 'logout']);
        Route::get('/jadwal', [ApiController::class, 'schedules']);
        Route::post('/ujian/{id}/masuk', [ApiController::class, 'start']);
        Route::get('/ujian/sesi/{sid}/soal', [ApiController::class, 'question']);
        Route::post('/ujian/sesi/{sid}/jawaban', [ApiController::class, 'save']);
        Route::post('/ujian/sesi/{sid}/sync', [ApiController::class, 'sync']);
        Route::post('/ujian/sesi/{sid}/ping', [ApiController::class, 'ping']);
        Route::post('/ujian/sesi/{sid}/submit', [ApiController::class, 'submit']);
        Route::post('/ujian/sesi/{sid}/event', [OperationsApiController::class, 'event']);
        Route::get('/monitoring/sesi-aktif', [OperationsApiController::class, 'active']);
        Route::get('/monitoring/live-score', [OperationsApiController::class, 'scores']);
        Route::get('/grading/isian', [OperationsApiController::class, 'pending']);
        Route::post('/grading/isian/{id}', [OperationsApiController::class, 'grade']);
        Route::get('/admin/siswa', [AdminApiController::class, 'students']);
        Route::post('/admin/siswa', [AdminApiController::class, 'storeStudent']);
        Route::post('/admin/users', [AdminApiController::class, 'storeUser']);
        Route::put('/admin/users/{id}', [AdminApiController::class, 'updateUser']);
        Route::get('/admin/roles', [AdminApiController::class, 'rolesPermissions']);
        Route::put('/admin/roles/{id}/permissions', [AdminApiController::class, 'syncRolePermissions']);
        Route::patch('/admin/users/{id}/kehadiran', [AdminApiController::class, 'attendance']);
        Route::delete('/admin/users/{id}', [AdminApiController::class, 'destroyUser']);
        Route::post('/admin/kelas', [AdminApiController::class, 'storeClass']);
        Route::get('/admin/master/{type}', [AdminApiController::class, 'masters']);
        Route::post('/admin/master/{type}', [AdminApiController::class, 'storeMaster']);
        Route::put('/admin/master/{type}/{id}', [AdminApiController::class, 'updateMaster']);
        Route::delete('/admin/master/{type}/{id}', [AdminApiController::class, 'destroyMaster']);
        Route::post('/admin/siswa/bulk-upload', [AdminApiController::class, 'bulkStudents']);
        Route::post('/guru/paket-soal/{id}/ready', [AdminApiController::class, 'readyPackage']);
        Route::get('/guru/paket-soal/{id}/preview', [AdminApiController::class, 'previewPackage']);
        Route::post('/guru/paket-soal/{id}/bulk-upload', [AdminApiController::class, 'bulkQuestions']);
        Route::post('/admin/master-ujian', [AdminApiController::class, 'storeMasterExam']);
        Route::post('/admin/jadwal', [AdminApiController::class, 'storeSchedule']);
        Route::get('/guru/paket-soal', [AdminApiController::class, 'packages']);
        Route::post('/guru/paket-soal', [AdminApiController::class, 'storePackage']);
        Route::post('/guru/paket-soal/{paket}/soal', [AdminApiController::class, 'storeQuestion']);
        Route::put('/guru/paket-soal/{paket}/soal/{id}', [AdminApiController::class, 'updateQuestion']);
        Route::delete('/guru/paket-soal/{paket}/soal/{id}', [AdminApiController::class, 'deleteQuestion']);
        Route::post('/admin/jadwal/{id}/regenerate-token', [AdminApiController::class, 'regenerate']);
        Route::post('/pengawas/sesi/{id}/reset', [AdminApiController::class, 'resetSession']);
    });
    Route::middleware(['auth:api', 'throttle:30,1'])->group(function () {
        Route::get('/laporan/{jadwal}', [OperationsApiController::class, 'report']);
        Route::get('/laporan/{jadwal}/{format}', [AdminApiController::class, 'report']);
    });
});


