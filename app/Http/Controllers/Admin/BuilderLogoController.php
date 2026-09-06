<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Builder;
use App\Services\BuilderService;
use Illuminate\Http\Request;

/**
 * Centralized "Builder Logo Library".
 *
 * Slim CRUD for the logos that appear on advisor public profiles.
 * Unlike BuilderController (which also tracks contacts for internal project
 * sourcing), this controller is purely for the public-profile logo strip:
 * admin uploads ONCE here, and by default every advisor shows every logo.
 * Per-advisor hide / feature toggles live on the advisor-profile edit page.
 */
class BuilderLogoController extends Controller
{
    protected BuilderService $builderService;

    public function __construct(BuilderService $builderService)
    {
        $this->middleware(['auth', 'role:admin,crm']);
        $this->builderService = $builderService;
    }

    public function index()
    {
        $builders = Builder::orderBy('status')
            ->orderBy('name')
            ->get();

        return view('admin.builder-logos.index', [
            'builders' => $builders,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:builders,name'],
            'description' => ['nullable', 'string', 'max:500'],
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:1024'],
        ]);

        $this->builderService->createBuilder(
            [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => 'active',
            ],
            $request->file('logo')
        );

        return redirect()
            ->route('admin.builder-logos.index')
            ->with('success', 'Builder logo upload ho gaya. Ab ye har advisor ke public profile par dikhega.');
    }

    public function update(Request $request, Builder $builderLogo)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:builders,name,' . $builderLogo->id],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:1024'],
        ]);

        $logo = $request->hasFile('logo') ? $request->file('logo') : null;

        $this->builderService->updateBuilder($builderLogo, [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
        ], $logo);

        return redirect()
            ->route('admin.builder-logos.index')
            ->with('success', 'Builder updated.');
    }

    public function destroy(Builder $builderLogo)
    {
        $this->builderService->deleteLogo($builderLogo);
        $builderLogo->delete();

        return redirect()
            ->route('admin.builder-logos.index')
            ->with('success', 'Builder removed.');
    }
}
