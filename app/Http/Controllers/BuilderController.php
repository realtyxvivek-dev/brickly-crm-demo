<?php

namespace App\Http\Controllers;

use App\Models\Builder;
use App\Services\BuilderService;
use Illuminate\Http\Request;

class BuilderController extends Controller
{
    protected $builderService;

    public function __construct(BuilderService $builderService)
    {
        $this->middleware('auth');
        $this->builderService = $builderService;
    }

    public function index()
    {
        $currentUser = request()->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        $builders = Builder::with('activeContacts')->latest()->paginate(15);

        return view('builders.index', compact('builders'));
    }

    public function create(Request $request)
    {
        $currentUser = $request->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        $returnTo = $request->get('return_to');
        return view('builders.form', ['builder' => null, 'return_to' => $returnTo]);
    }

    public function store(Request $request)
    {
        $currentUser = $request->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:builders,name',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string|max:5000',
            'status' => 'nullable|in:active,inactive',
            'contacts' => 'required|array|min:1|max:5',
            'contacts.*.person_name' => 'required|string|max:255',
            'contacts.*.mobile_number' => 'required|string|max:15',
            'contacts.*.whatsapp_number' => 'nullable|string|max:15',
            'contacts.*.whatsapp_same_as_mobile' => 'nullable|boolean',
            'contacts.*.preferred_mode' => 'nullable|in:call,whatsapp,both',
            'contacts.*.is_active' => 'nullable|boolean',
        ]);

        $logo = $request->hasFile('logo') ? $request->file('logo') : null;
        $contacts = $validated['contacts'];
        unset($validated['contacts']);

        $builder = $this->builderService->createBuilder($validated, $logo);

        foreach ($contacts as $contactData) {
            $this->builderService->addContact($builder, $contactData);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json($builder->load('activeContacts'), 201);
        }

        // Check if we need to return to project form
        if ($request->has('return_to') && $request->return_to === 'project_form') {
            return redirect()->route('projects.create')
                ->with('success', 'Builder created successfully!')
                ->with('selected_builder_id', $builder->id);
        }

        return redirect()->route('builders.index')
            ->with('success', 'Builder created successfully.');
    }

    public function show(Builder $builder)
    {
        $builder->load('activeContacts', 'projects');
        return view('builders.show', compact('builder'));
    }

    public function edit(Builder $builder)
    {
        $currentUser = request()->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        $builder->load('contacts'); // Load all contacts, not just active
        return view('builders.form', ['builder' => $builder]);
    }

    public function update(Request $request, Builder $builder)
    {
        $currentUser = $request->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:builders,name,' . $builder->id,
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string|max:5000',
            'status' => 'nullable|in:active,inactive',
            'contacts' => 'sometimes|array|min:1|max:5',
            'contacts.*.id' => 'nullable|exists:builder_contacts,id',
            'contacts.*.person_name' => 'required|string|max:255',
            'contacts.*.mobile_number' => 'required|string|max:15',
            'contacts.*.whatsapp_number' => 'nullable|string|max:15',
            'contacts.*.whatsapp_same_as_mobile' => 'nullable|boolean',
            'contacts.*.preferred_mode' => 'nullable|in:call,whatsapp,both',
            'contacts.*.is_active' => 'nullable|boolean',
        ]);

        $logo = $request->hasFile('logo') ? $request->file('logo') : null;
        $contacts = $validated['contacts'] ?? null;
        unset($validated['contacts']);

        $builder = $this->builderService->updateBuilder($builder, $validated, $logo);

        // Handle contacts update
        if ($contacts) {
            // Delete removed contacts
            $existingIds = collect($contacts)->pluck('id')->filter();
            $builder->contacts()->whereNotIn('id', $existingIds)->delete();

            // Update or create contacts
            foreach ($contacts as $contactData) {
                if (isset($contactData['id']) && $contactData['id']) {
                    $contact = $builder->contacts()->find($contactData['id']);
                    if ($contact) {
                        $this->builderService->updateContact($contact, $contactData);
                    }
                } else {
                    $this->builderService->addContact($builder, $contactData);
                }
            }
        }

        return redirect()->route('builders.index')
            ->with('success', 'Builder updated successfully.');
    }

    public function destroy(Builder $builder)
    {
        $currentUser = request()->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isCrm()) {
            abort(403, 'Unauthorized action.');
        }

        if ($builder->projects()->count() > 0) {
            return redirect()->route('builders.index')
                ->with('error', 'Cannot delete builder with existing projects.');
        }

        $builder->delete();

        return redirect()->route('builders.index')
            ->with('success', 'Builder deleted successfully.');
    }
}
