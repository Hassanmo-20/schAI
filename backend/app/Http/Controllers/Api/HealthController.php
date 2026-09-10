<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Public liveness probe. Deliberately minimal: environment name, app name
     * and driver details are withheld so an unauthenticated caller learns
     * nothing useful about the deployment.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => $this->databaseIsReachable() ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Attempt a lightweight connection so a broken database is visible here
     * rather than only surfacing on the first real request.
     */
    private function databaseIsReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
