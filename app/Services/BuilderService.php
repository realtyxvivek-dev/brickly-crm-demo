<?php

namespace App\Services;

use App\Models\Builder;
use App\Models\BuilderContact;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class BuilderService
{
    /**
     * Create a new builder.
     */
    public function createBuilder(array $data, ?UploadedFile $logo = null): Builder
    {
        DB::beginTransaction();
        try {
            $builder = Builder::create($data);

            if ($logo) {
                $this->uploadLogo($builder, $logo);
            }

            DB::commit();
            return $builder;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a builder.
     */
    public function updateBuilder(Builder $builder, array $data, ?UploadedFile $logo = null): Builder
    {
        DB::beginTransaction();
        try {
            $builder->update($data);

            if ($logo) {
                $this->uploadLogo($builder, $logo);
            }

            DB::commit();
            return $builder->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Upload builder logo.
     */
    public function uploadLogo(Builder $builder, UploadedFile $logo): string
    {
        // Delete old logo if exists
        if ($builder->logo) {
            $this->deleteLogo($builder);
        }

        // Generate unique filename
        $filename = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();

        // Store logo
        $path = $logo->storeAs('builders/logos', $filename, 'public');

        // Update builder
        $builder->update(['logo' => $filename]);

        return $filename;
    }

    /**
     * Delete builder logo.
     */
    public function deleteLogo(Builder $builder): void
    {
        if ($builder->logo && Storage::disk('public')->exists('builders/logos/' . $builder->logo)) {
            Storage::disk('public')->delete('builders/logos/' . $builder->logo);
        }

        $builder->update(['logo' => null]);
    }

    /**
     * Add contact to builder.
     */
    public function addContact(Builder $builder, array $data): BuilderContact
    {
        $this->validateMaxContacts($builder);

        // Auto-fill WhatsApp if same as mobile
        if (isset($data['whatsapp_same_as_mobile']) && $data['whatsapp_same_as_mobile']) {
            $data['whatsapp_number'] = $data['mobile_number'];
        }

        return $builder->contacts()->create($data);
    }

    /**
     * Update builder contact.
     */
    public function updateContact(BuilderContact $contact, array $data): BuilderContact
    {
        // Auto-fill WhatsApp if same as mobile
        if (isset($data['whatsapp_same_as_mobile']) && $data['whatsapp_same_as_mobile']) {
            $data['whatsapp_number'] = $data['mobile_number'];
        }

        $contact->update($data);
        return $contact->fresh();
    }

    /**
     * Validate max contacts (5 active).
     */
    public function validateMaxContacts(Builder $builder): void
    {
        $activeCount = $builder->activeContacts()->count();

        if ($activeCount >= 5) {
            throw new \Exception('Maximum 5 active contacts allowed per builder.');
        }
    }

    /**
     * Get active contacts for builder.
     */
    public function getActiveContacts(Builder $builder)
    {
        return $builder->activeContacts()->get();
    }
}
