<?php

namespace App\Http\Controllers;

use App\Services\ProjectUrlImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use RuntimeException;

class ProjectUrlImportController extends Controller
{
    public function __construct(
        private readonly ProjectUrlImportService $service,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->authorizeImport();

        return view('projects.import-url');
    }

    public function extract(Request $request): JsonResponse
    {
        $this->authorizeImport();

        $request->validate([
            'source_url' => 'required|url|max:2000',
        ]);

        try {
            $result = $this->service->extractFromUrl(
                (string) $request->input('source_url'),
                (int) optional($request->user())->id
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'URL extracted successfully.',
            'token' => $result['token'],
            'review_url' => $result['review_url'],
            'summary' => $result['summary'],
        ]);
    }

    public function review(string $token)
    {
        $this->authorizeImport();

        return view('projects.import-url-review', [
            'review' => $this->service->reviewPayload($token),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $this->authorizeImport();

        $request->validate([
            'review_token' => 'required|string',
        ]);

        try {
            $project = $this->service->createDraftProject(
                (string) $request->input('review_token'),
                $request->all()
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Draft project created successfully.',
            'project_id' => $project->id,
            'wizard_url' => route('projects.edit', $project),
        ]);
    }

    public function downloadImages(string $token): BinaryFileResponse
    {
        $this->authorizeImport();

        try {
            $archive = $this->service->prepareDetectedImagesDownload($token);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->download($archive['path'], $archive['filename'])->deleteFileAfterSend(true);
    }

    private function authorizeImport(): void
    {
        $user = request()->user();

        if (!$user || (!$user->isAdmin() && !$user->isCrm())) {
            abort(403, 'Unauthorized action.');
        }
    }
}
