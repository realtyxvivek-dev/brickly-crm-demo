<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Builder;
use App\Models\BuilderContact;
use App\Services\BuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BuilderController extends Controller
{
    protected $builderService;

    public function __construct(BuilderService $builderService)
    {
        $this->builderService = $builderService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Builder::with('activeContacts');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));
        $builders = $query->latest()->paginate($perPage);

        return response()->json($builders);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
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

        // Add contacts
        foreach ($contacts as $contactData) {
            $this->builderService->addContact($builder, $contactData);
        }

        return response()->json($builder->load('activeContacts'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Builder $builder)
    {
        $builder->load('activeContacts', 'projects');
        return response()->json($builder);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Builder $builder)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:builders,name,' . $builder->id,
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'description' => 'nullable|string|max:5000',
            'status' => 'nullable|in:active,inactive',
        ]);

        $logo = $request->hasFile('logo') ? $request->file('logo') : null;

        $builder = $this->builderService->updateBuilder($builder, $validated, $logo);

        return response()->json($builder->load('activeContacts'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Builder $builder)
    {
        $user = request()->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Check if builder has projects
        if ($builder->projects()->count() > 0) {
            return response()->json(['message' => 'Cannot delete builder with existing projects'], 400);
        }

        $builder->delete();

        return response()->json(['message' => 'Builder deleted successfully']);
    }

    /**
     * Upload logo for builder.
     */
    public function uploadLogo(Request $request, Builder $builder)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $filename = $this->builderService->uploadLogo($builder, $request->file('logo'));

        return response()->json([
            'message' => 'Logo uploaded successfully',
            'logo' => $filename,
            'logo_url' => $builder->fresh()->logo_url,
        ]);
    }

    /**
     * Add contact to builder.
     */
    public function addContact(Request $request, Builder $builder)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'person_name' => 'required|string|max:255',
            'mobile_number' => 'required|string|max:15',
            'whatsapp_number' => 'nullable|string|max:15',
            'whatsapp_same_as_mobile' => 'nullable|boolean',
            'preferred_mode' => 'nullable|in:call,whatsapp,both',
            'is_active' => 'nullable|boolean',
        ]);

        $contact = $this->builderService->addContact($builder, $validated);

        return response()->json($contact, 201);
    }

    /**
     * Update builder contact.
     */
    public function updateContact(Request $request, Builder $builder, BuilderContact $contact)
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($contact->builder_id !== $builder->id) {
            return response()->json(['message' => 'Contact does not belong to this builder'], 400);
        }

        $validated = $request->validate([
            'person_name' => 'sometimes|string|max:255',
            'mobile_number' => 'sometimes|string|max:15',
            'whatsapp_number' => 'nullable|string|max:15',
            'whatsapp_same_as_mobile' => 'nullable|boolean',
            'preferred_mode' => 'sometimes|in:call,whatsapp,both',
            'is_active' => 'sometimes|boolean',
        ]);

        $contact = $this->builderService->updateContact($contact, $validated);

        return response()->json($contact);
    }

    /**
     * Delete builder contact.
     */
    public function deleteContact(Builder $builder, BuilderContact $contact)
    {
        $user = request()->user();

        if (!$user->isAdmin() && !$user->isCrm()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($contact->builder_id !== $builder->id) {
            return response()->json(['message' => 'Contact does not belong to this builder'], 400);
        }

        // Check if contact is used in projects
        if ($contact->projectContacts()->count() > 0) {
            return response()->json(['message' => 'Cannot delete contact used in projects'], 400);
        }

        $contact->delete();

        return response()->json(['message' => 'Contact deleted successfully']);
    }
}
