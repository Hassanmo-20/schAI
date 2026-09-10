<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BatchController extends Controller
{
    /**
     * Public list of batches used by the registration form.
     *
     * Registration requires a valid `batch_id`, so the client needs some way to
     * discover them. Only id/name/department are exposed — no membership data,
     * no counts, nothing that would help enumerate users.
     */
    public function index(): AnonymousResourceCollection
    {
        return BatchResource::collection(
            Batch::query()->orderBy('name')->get(['id', 'name', 'department', 'academic_year'])
        );
    }
}
