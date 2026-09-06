<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbCustomMappingField extends Model
{
    protected $table = 'fb_custom_mapping_fields';

    protected $fillable = ['field_key', 'label'];

    /**
     * Get all custom field keys for dropdown (including label for display).
     */
    public static function getKeysForMapping(): array
    {
        return self::orderBy('field_key')->get()
            ->mapWithKeys(fn ($row) => [$row->field_key => $row->label ?: $row->field_key])
            ->all();
    }
}
