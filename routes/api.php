<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CampController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\TransferRequestController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;

Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {

    Route::get('/profile', [AuthController::class, 'profile']);

    Route::post('/logout', [AuthController::class, 'logout']);

});


Route::middleware('auth:sanctum')->group(function(){

    Route::middleware(['role:admin'])->get(
        'users/statistics',
        [UserController::class,'statistics']
    );

    Route::middleware(['role:admin'])->apiResource(
        'users',
        UserController::class
    );


    Route::middleware(['role:admin'])->post(
        'users/{id}/toggle-status',
        [UserController::class,'toggleStatus']
    );


});


Route::middleware('auth:sanctum')->group(function(){

    Route::get('camps', [CampController::class, 'index']);

    Route::middleware(['role:admin'])->apiResource(
        'camps',
        CampController::class
    )->except(['index']);


});

Route::middleware('auth:sanctum')->group(function () {

     Route::apiResource(
        'families',
        FamilyController::class
    );

    Route::get('/families/check/{national_id}',
        [FamilyController::class,'checkNationalId']);

        Route::post('/families/{family}/members',
[FamilyController::class,'addMember']);


Route::put('/members/{member}',
[FamilyController::class,'updateMember']);


Route::delete('/members/{member}',
[FamilyController::class,'deleteMember']);
});



Route::middleware(['auth:sanctum'])
->group(function(){


Route::get(
'transfer-requests',
[
TransferRequestController::class,
'index'
]
);


Route::middleware(['role:data_entry'])
->post(
'transfer-requests',
[
TransferRequestController::class,
'store'
]
);


Route::middleware(['role:manager,admin'])
->group(function(){


Route::put(
'transfer-requests/{id}/approve',
[
TransferRequestController::class,
'approve'
]
);

Route::patch(
'transfer-requests/{id}/approve',
[
TransferRequestController::class,
'approve'
]
);



Route::put(
'transfer-requests/{id}/reject',
[
TransferRequestController::class,
'reject'
]
);

Route::patch(
'transfer-requests/{id}/reject',
[
TransferRequestController::class,
'reject'
]
);



});


});





Route::middleware('auth:sanctum')->group(function(){



    Route::get(
        '/search/local',
        [SearchController::class,'local']
    );



    Route::get(
        '/search/global',
        [SearchController::class,'global']
    )->middleware(['role:manager,admin']);


});

// SELECT CAMP FOR DATA ENTRY USER WHEN THEY LOGIN
Route::middleware('auth:sanctum')->group(function(){
    Route::patch('/user/select-camp', [UserController::class, 'selectCamp'])->middleware(['role:data_entry']);
});


// REPORTS ROUTES
Route::middleware('auth:sanctum')->prefix('reports')->group(function(){

    Route::get('/demographic',
        [ReportController::class,'demographic']
    )->middleware(['role:manager,admin']);

    //export excel demographic report
Route::get('/demographic/export/excel',
 [ReportController::class, 'exportDemographicExcel']
 )->middleware(['role:manager,admin']);
//export pdf demographic report
 Route::get('/demographic/export/pdf',
  [ReportController::class, 'exportDemographicPdf']
  )->middleware(['role:manager,admin']);

    Route::get('/vulnerability',
        [ReportController::class,'vulnerability']
    )->middleware(['role:manager,admin']);

//export excel vulnerability report
    Route::get('/vulnerability/export/excel',
     [ReportController::class, 'exportVulnerabilityExcel']
     )->middleware(['role:manager,admin']);

     //export pdf vulnerability report
Route::get('/vulnerability/export/pdf',
 [ReportController::class, 'exportVulnerabilityPdf']
 )->middleware(['role:manager,admin']);

    Route::get('/transfers',
        [ReportController::class,'transfers']
    )->middleware(['role:manager,admin']);

//export excel transfers report
Route::get('/transfers/export/excel', 
[ReportController::class, 'exportTransfersExcel']
)->middleware(['role:manager,admin']);
//export pdf transfers report
Route::get('/transfers/export/pdf',
 [ReportController::class, 'exportTransfersPdf']
 )->middleware(['role:manager,admin']);

    Route::get('/periodic',
        [ReportController::class,'periodic']
    )->middleware(['role:manager,admin']);

//export excel periodic report
    Route::get('/periodic/export/excel'
    , [ReportController::class, 'exportPeriodicExcel']
    )->middleware(['role:manager,admin']);
//export pdf periodic report
    Route::get('/periodic/export/pdf', 
    [ReportController::class, 'exportPeriodicPdf']
    )->middleware(['role:manager,admin']);

});



Route::middleware('auth:sanctum')->get('/dashboard', [DashboardController::class, 'index']);
