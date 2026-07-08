<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActiveBranchManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ActiveBranchController extends Controller
{
    public function update(Request $request, ActiveBranchManager $activeBranchManager): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if (! $activeBranchManager->canUseBranch($user, $data['tenant_id'], (int) $data['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => 'Select a branch you have access to.',
            ]);
        }

        $activeBranchManager->set($request, $data['tenant_id'], (int) $data['branch_id']);

        return back()->with('status', 'Active branch updated.');
    }
}
