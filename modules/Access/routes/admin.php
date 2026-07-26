<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Access\Http\Controllers\AccessReviewController;
use Modules\Access\Http\Controllers\ApprovalController;
use Modules\Access\Http\Controllers\RoleController;

Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
Route::post('/roles/{role}/duplicate', [RoleController::class, 'duplicate'])->name('roles.duplicate');
Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

Route::post('/tenant-users', [RoleController::class, 'storeTenantUser'])->name('tenant-users.store');
Route::put('/memberships/{membership}', [RoleController::class, 'updateMembership'])->name('memberships.update');

Route::get('/review', [AccessReviewController::class, 'index'])->name('review.index');

Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
Route::post('/approvals/{approvalRequest}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
Route::post('/approvals/{approvalRequest}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
