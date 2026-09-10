<?php

namespace App\Http\Controllers\Api;

use App\Enums\BatchYear;
use App\Enums\Department;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BatchController extends Controller
{
    /**
     * Public list of existing groups.
     *
     * Registration submits a (batch year, department) pair rather than an id,
     * so this is informational only — but it stays public because the sign-up
     * screen shows which groups already exist. Only descriptive fields are
     * exposed: no membership data, no counts, nothing that helps enumerate
     * users.
     */
    public function index(): AnonymousResourceCollection
    {
        return BatchResource::collection(
            Batch::query()
                ->orderBy('batch_year')
                ->orderBy('department')
                ->orderBy('name')
                ->get(['id', 'name', 'batch_year', 'department', 'academic_year'])
        );
    }

    /**
     * The choices the registration form must offer.
     *
     * Served from the enums rather than the batches table so the form is
     * correct even before any group has members — and so the backend stays
     * the single source of truth for what a valid batch/department/role is.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'batch_years' => BatchYear::values(),
                'departments' => array_map(
                    fn (Department $department) => [
                        'value' => $department->value,
                        'label' => $department->label(),
                    ],
                    Department::cases()
                ),
                'roles' => [
                    ['value' => Role::Student->value, 'label' => 'Student'],
                    ['value' => Role::Representative->value, 'label' => 'Batch Representative'],
                ],
            ],
        ]);
    }
}
