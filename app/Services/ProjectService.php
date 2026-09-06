<?php

namespace App\Services;

use App\Models\Project;
use App\Models\BuilderContact;
use App\Models\Tower;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProjectService
{
    /**
     * Create a new project.
     */
    public function createProject(array $data, ?array $contactIds = null, $logo = null): Project
    {
        DB::beginTransaction();
        try {
            // Handle logo upload
            if ($logo) {
                $data['logo'] = $this->uploadLogo(null, $logo);
            }

            $project = Project::create($data);

            if ($contactIds) {
                $this->assignContacts($project, $contactIds);
            }

            DB::commit();
            return $project;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a project.
     */
    public function updateProject(Project $project, array $data, ?array $contactIds = null, $logo = null): Project
    {
        DB::beginTransaction();
        try {
            // Handle logo upload
            if ($logo) {
                $this->deleteLogo($project);
                $data['logo'] = $this->uploadLogo($project, $logo);
            }

            $project->update($data);

            if ($contactIds !== null) {
                $this->assignContacts($project, $contactIds);
            }

            DB::commit();
            return $project->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Assign contacts to project.
     */
    public function assignContacts(Project $project, array $contactIds): void
    {
        $contactIds = array_filter($contactIds);

        // Clear existing contacts
        $project->projectContacts()->delete();

        if (!$project->builder_id || empty($contactIds)) {
            return;
        }

        // Assign new contacts
        foreach ($contactIds as $role => $builderContactId) {
            if ($builderContactId) {
                // Verify contact belongs to project's builder
                $contact = BuilderContact::where('id', $builderContactId)
                    ->where('builder_id', $project->builder_id)
                    ->first();

                if (!$contact) {
                    throw new \Exception("Contact does not belong to this builder.");
                }

                $project->projectContacts()->create([
                    'builder_contact_id' => $builderContactId,
                    'contact_role' => $role,
                ]);
            }
        }
    }

    /**
     * Validate primary contact exists.
     */
    public function validatePrimaryContact(Project $project): bool
    {
        return $project->primaryContact() !== null;
    }

    /**
     * Get contacts by role.
     */
    public function getContactsByRole(Project $project, string $role): ?\App\Models\ProjectContact
    {
        return $project->projectContacts()->where('contact_role', $role)->first();
    }

    /**
     * Update configuration summary.
     */
    public function updateConfigurationSummary(Project $project, array $configSummary): void
    {
        $project->update(['configuration_summary' => $configSummary]);
    }

    /**
     * Upload project logo.
     */
    public function uploadLogo(?Project $project, $file): string
    {
        $path = $file->store('project-logos', 'public');
        return $path;
    }

    /**
     * Delete project logo.
     */
    public function deleteLogo(Project $project): void
    {
        if ($project->logo && Storage::disk('public')->exists($project->logo)) {
            Storage::disk('public')->delete($project->logo);
        }
    }

    /**
     * Create a tower for a project.
     */
    public function createTower(Project $project, array $data): Tower
    {
        $data['project_id'] = $project->id;
        return Tower::create($data);
    }

    /**
     * Update a tower.
     */
    public function updateTower(Tower $tower, array $data): Tower
    {
        $tower->update($data);
        return $tower->fresh();
    }

    /**
     * Delete a tower.
     */
    public function deleteTower(Tower $tower): void
    {
        $tower->delete();
    }
}
