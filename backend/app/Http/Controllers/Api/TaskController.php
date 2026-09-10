<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    /**
     * Batch-scoped listing. Students see active tasks only; representatives
     * see their whole batch (including deactivated) with completion aggregates.
     * Ordered incomplete-first, then nearest deadline — computed in SQL.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Task::class);

        $user = $request->user();
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $query = Task::query()
            ->forBatch($user->batch_id)
            ->leftJoin('task_completions', function ($join) use ($user) {
                $join->on('task_completions.task_id', '=', 'tasks.id')
                    ->where('task_completions.student_id', '=', $user->id);
            })
            ->select('tasks.*', 'task_completions.completed_at as viewer_completed_at')
            ->with(['attachments', 'creator'])
            ->orderByRaw('task_completions.id IS NOT NULL')
            ->orderBy('tasks.deadline')
            ->orderBy('tasks.id');

        if ($user->isStudent()) {
            $query->where('tasks.is_active', true);
        } else {
            $query->withCount('completions as completed_students');
        }

        $tasks = $query->paginate($perPage)->withQueryString();

        if ($user->isRepresentative()) {
            $totalStudents = User::where('batch_id', $user->batch_id)
                ->where('role', 'student')
                ->count();
            $tasks->getCollection()->each(
                fn (Task $task) => $task->setAttribute('total_students', $totalStudents)
            );
        }

        return TaskResource::collection($tasks);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        Gate::authorize('create', Task::class);

        $task = DB::transaction(function () use ($request) {
            $task = Task::create([
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'type' => $request->validated('type'),
                'deadline' => $request->validated('deadline'),
                // Ownership is derived server-side — never accepted from the client.
                'batch_id' => $request->user()->batch_id,
                'created_by' => $request->user()->id,
                'is_active' => true,
            ]);

            $this->storeAttachments($task, $request);

            return $task;
        });

        $task->load(['attachments', 'creator']);

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    public function show(Request $request, Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        $task->load(['attachments', 'creator']);
        $task->setAttribute('viewer_completed_at', $this->viewerCompletedAt($task->id, $request->user()->id));

        return new TaskResource($task);
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        Gate::authorize('update', $task);

        DB::transaction(function () use ($request, $task) {
            // Only validated scalar fields are updatable: created_by, batch_id
            // and completion data can never change through this endpoint.
            $task->update($request->safe()->only(['title', 'description', 'type', 'deadline', 'is_active']));
            $this->storeAttachments($task, $request);
        });

        $task->refresh()->load(['attachments', 'creator']);
        $task->setAttribute('viewer_completed_at', $this->viewerCompletedAt($task->id, $request->user()->id));

        return new TaskResource($task);
    }

    /**
     * Deactivation (not destruction): the row and its completion history stay
     * intact while students stop seeing the task.
     */
    public function destroy(Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);

        $task->update(['is_active' => false]);

        return response()->json(['message' => 'Task deactivated']);
    }

    public function downloadAttachment(Task $task, TaskAttachment $attachment): StreamedResponse
    {
        Gate::authorize('downloadAttachment', $task);

        $this->assertAttachmentBelongsToTask($task, $attachment);

        $disk = Storage::disk(config('filesystems.default'));

        if (! $disk->exists($attachment->file_path)) {
            abort(404, 'File no longer available');
        }

        return $disk->download($attachment->file_path, $attachment->original_name);
    }

    /**
     * Remove a single attachment. Representatives need this because updates
     * only append files — without it stored files could never be removed.
     */
    public function destroyAttachment(Task $task, TaskAttachment $attachment): JsonResponse
    {
        Gate::authorize('update', $task);

        $this->assertAttachmentBelongsToTask($task, $attachment);

        DB::transaction(function () use ($attachment) {
            // Delete the row first: if the file is already gone we still clear
            // the dangling record instead of failing the request.
            $path = $attachment->file_path;
            $attachment->delete();
            Storage::disk(config('filesystems.default'))->delete($path);
        });

        return response()->json(['message' => 'Attachment deleted']);
    }

    /**
     * Route model binding resolves the attachment independently of the task,
     * so the ownership link must be verified explicitly (IDOR guard).
     */
    private function assertAttachmentBelongsToTask(Task $task, TaskAttachment $attachment): void
    {
        if ($attachment->task_id !== $task->id) {
            abort(404);
        }
    }

    /**
     * Persist validated uploads under framework-generated hashed paths.
     * Client filenames are stored as display metadata only.
     */
    private function storeAttachments(Task $task, StoreTaskRequest|UpdateTaskRequest $request): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('task-attachments', config('filesystems.default'));

            $task->attachments()->create([
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize() ?? 0,
            ]);
        }
    }

    private function viewerCompletedAt(int $taskId, int $userId): ?string
    {
        $completedAt = DB::table('task_completions')
            ->where('task_id', $taskId)
            ->where('student_id', $userId)
            ->value('completed_at');

        return $completedAt !== null ? (string) $completedAt : null;
    }
}
