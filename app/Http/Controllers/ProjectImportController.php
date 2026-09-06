<?php

namespace App\Http\Controllers;

use App\Services\ProjectImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectImportController extends Controller
{
    public function __construct(
        private readonly ProjectImportService $service,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorizeImport();

        return view('projects.import');
    }

    public function template(): BinaryFileResponse
    {
        $this->authorizeImport();

        $path = $this->service->createTemplate();

        return response()->download($path, 'project-import-template.xlsx')->deleteFileAfterSend(true);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorizeImport();

        $request->validate([
            'import_file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        $preview = $this->service->previewImport($request->file('import_file'));

        return response()->json([
            'success' => true,
            'message' => 'Import preview generated successfully.',
            'token' => $preview['token'],
            'preview' => $preview['summary'],
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $this->authorizeImport();

        $request->validate([
            'preview_token' => 'required|string',
        ]);

        $project = $this->service->createDraftProject((string) $request->input('preview_token'));

        return response()->json([
            'success' => true,
            'message' => 'Draft project created successfully.',
            'project_id' => $project->id,
            'wizard_url' => route('projects.edit', $project),
        ]);
    }

    private function authorizeImport(): void
    {
        $user = request()->user();

        if (!$user || (!$user->isAdmin() && !$user->isCrm())) {
            abort(403, 'Unauthorized action.');
        }
    }
}
