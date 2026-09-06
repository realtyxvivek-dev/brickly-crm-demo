<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalaryRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_profile_id',
        'user_id',
        'user_salary_profile_id',
        'salary_structure_id',
        'previous_base_salary',
        'new_base_salary',
        'previous_total_salary',
        'new_total_salary',
        'effective_from',
        'reason',
        'notes',
        'changed_by',
    ];

    protected $casts = [
        'previous_base_salary' => 'decimal:2',
        'new_base_salary' => 'decimal:2',
        'previous_total_salary' => 'decimal:2',
        'new_total_salary' => 'decimal:2',
        'effective_from' => 'date',
    ];

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function salaryProfile(): BelongsTo
    {
        return $this->belongsTo(UserSalaryProfile::class, 'user_salary_profile_id');
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
