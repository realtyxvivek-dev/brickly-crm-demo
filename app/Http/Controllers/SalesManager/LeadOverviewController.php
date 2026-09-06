<?php

namespace App\Http\Controllers\SalesManager;

use App\Http\Controllers\Controller;
use App\Services\SalesManagerLeadKanbanService;
use Illuminate\Http\Request;

class LeadOverviewController extends Controller
{
    public function index(Request $request, SalesManagerLeadKanbanService $service)
    {
        abort_unless($service->isEnabledFor($request->user()), 403);

        $filters = $service->filters($request);
        $data = $service->build($request->user(), $filters);
        $data = array_merge($data, $this->viewContext($request));

        return view('sales-manager.overview', $data);
    }

    public function data(Request $request, SalesManagerLeadKanbanService $service)
    {
        abort_unless($service->isEnabledFor($request->user()), 403);

        $filters = $service->filters($request);
        $data = $service->build($request->user(), $filters);
        $data = array_merge($data, $this->viewContext($request));

        return response()->json([
            'html' => view('sales-manager.overview._board', $data)->render(),
        ]);
    }

    private function viewContext(Request $request): array
    {
        $routeName = (string) optional($request->route())->getName();
        $user = $request->user();

        if ($user?->isAdmin() || $user?->isCrm()) {
            return [
                'leadBoardLayout' => 'layouts.app',
                'leadBoardDataRoute' => $user->isCrm() ? 'crm.lead-board.data' : 'admin.lead-board.data',
            ];
        }

        return [
            'leadBoardLayout' => 'sales-manager.layout',
            'leadBoardDataRoute' => str_starts_with($routeName, 'sales-manager.')
                ? 'sales-manager.overview.data'
                : 'sales-manager.overview.data',
        ];
    }
}
