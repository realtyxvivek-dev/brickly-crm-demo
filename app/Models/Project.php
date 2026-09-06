<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'builder_id',
        'name',
        'logo',
        'short_overview',
        'project_type',
        'residential_sub_type',
        'project_status',
        'availability_type',
        'city',
        'area',
        'land_area',
        'land_area_unit',
        'rera_no',
        'rera_qr_path',
        'possession_date',
        'project_highlights',
        'configuration_summary',
        'is_active',
    ];

    protected $casts = [
        'land_area' => 'decimal:2',
        'possession_date' => 'date',
        'configuration_summary' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the builder that owns the project.
     */
    public function builder(): BelongsTo
    {
        return $this->belongsTo(Builder::class);
    }

    /**
     * Get the project contacts.
     */
    public function projectContacts(): HasMany
    {
        return $this->hasMany(ProjectContact::class);
    }

    /**
     * Get the primary contact.
     */
    public function primaryContact(): ?ProjectContact
    {
        return $this->projectContacts()->where('contact_role', 'primary')->first();
    }

    /**
     * Get the secondary contact.
     */
    public function secondaryContact(): ?ProjectContact
    {
        return $this->projectContacts()->where('contact_role', 'secondary')->first();
    }

    /**
     * Get the escalation contact.
     */
    public function escalationContact(): ?ProjectContact
    {
        return $this->projectContacts()->where('contact_role', 'escalation')->first();
    }

    /**
     * Get the collaterals for the project.
     */
    public function collaterals(): HasMany
    {
        return $this->hasMany(ProjectCollateral::class);
    }

    /**
     * Get the pricing config for the project.
     */
    public function pricingConfig(): HasOne
    {
        return $this->hasOne(PricingConfig::class);
    }

    /**
     * Get the towers for the project.
     */
    public function towers(): HasMany
    {
        return $this->hasMany(Tower::class);
    }

    /**
     * Get active towers.
     */
    public function activeTowers(): HasMany
    {
        return $this->hasMany(Tower::class)->where('is_active', true)->whereNull('deleted_at');
    }

    /**
     * Get towers with unit types count.
     */
    public function towersWithUnits(): HasMany
    {
        return $this->towers()->withCount('unitTypes');
    }

    /**
     * Get the unit types for the project (direct, not in towers).
     */
    public function unitTypes(): HasMany
    {
        return $this->hasMany(UnitType::class)->whereNull('tower_id');
    }

    /**
     * Get all unit types (including tower units).
     */
    public function allUnitTypes(): HasMany
    {
        return $this->hasMany(UnitType::class);
    }

    /**
     * Get active unit types.
     */
    public function activeUnitTypes(): HasMany
    {
        return $this->hasMany(UnitType::class)->whereNull('deleted_at');
    }

    /**
     * Get the starting from unit (cheapest).
     */
    public function startingFromUnit(): ?UnitType
    {
        return $this->unitTypes()->where('is_starting_from', true)->first();
    }

    /**
     * Get formatted location.
     */
    public function getFormattedLocationAttribute(): ?string
    {
        if (!$this->city && !$this->area) {
            return null;
        }
        
        if ($this->city && $this->area) {
            return $this->city . ', ' . $this->area;
        }
        
        return $this->city ?: $this->area;
    }

    /**
     * Get formatted land area.
     */
    public function getFormattedLandAreaAttribute(): ?string
    {
        if (!$this->land_area) {
            return null;
        }

        return number_format($this->land_area, 2) . ' ' . ($this->land_area_unit === 'acres' ? 'Acres' : 'Sq.ft');
    }

    /**
     * Check if project is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Get logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) {
            return null;
        }

        if (str_starts_with($this->logo, 'http')) {
            return $this->logo;
        }

        return asset('storage/' . $this->logo);
    }

    /**
     * Get RERA QR image URL.
     */
    public function getReraQrUrlAttribute(): ?string
    {
        if (!$this->rera_qr_path) {
            return null;
        }

        if (str_starts_with($this->rera_qr_path, 'http')) {
            return $this->rera_qr_path;
        }

        return asset('storage/' . $this->rera_qr_path);
    }

    /**
     * Check if project has towers (for flats).
     */
    public function hasTowers(): bool
    {
        return $this->residential_sub_type === 'flat' && $this->towers()->count() > 0;
    }

    public function publicPage(): HasOne
    {
        return $this->hasOne(ProjectPublicPage::class);
    }

    public function publicUnitTypes(): HasMany
    {
        return $this->hasMany(ProjectUnitType::class)->orderBy('display_order');
    }

    public function sizeVariants(): HasMany
    {
        return $this->hasMany(ProjectSizeVariant::class)->orderBy('display_order');
    }

    public function publicAssets(): HasMany
    {
        return $this->hasMany(ProjectAsset::class)->orderBy('display_order');
    }

    public function publicLandmarks(): HasMany
    {
        return $this->hasMany(ProjectLandmark::class)->orderBy('display_order');
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(ProjectShareLink::class);
    }

    public function pageEvents(): HasMany
    {
        return $this->hasMany(ProjectPageEvent::class);
    }
}
