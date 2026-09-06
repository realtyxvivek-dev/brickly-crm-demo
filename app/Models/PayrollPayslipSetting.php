<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollPayslipSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'payslip_prefix',
        'company_name',
        'header_text',
        'footer_text',
        'default_notes',
        'signatory_name',
        'signatory_title',
        'signatory_image_path',
    ];
}
