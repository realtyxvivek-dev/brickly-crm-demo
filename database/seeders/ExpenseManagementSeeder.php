<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use Illuminate\Database\Seeder;

class ExpenseManagementSeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            ['name' => 'BIH Infra', 'code' => 'BIH_INFRA', 'sort_order' => 1],
            ['name' => 'BIH Marketing', 'code' => 'BIH_MARKETING', 'sort_order' => 2],
            ['name' => 'BIH Corporate', 'code' => 'BIH_CORPORATE', 'sort_order' => 3],
        ];

        foreach ($companies as $company) {
            Company::updateOrCreate(
                ['code' => $company['code']],
                $company + ['is_active' => true]
            );
        }

        $categoryMap = [
            'Electricity' => [
                'code' => 'ELECTRICITY',
                'sort_order' => 1,
                'subcategories' => [
                    ['name' => 'UPPCL', 'code' => 'UPPCL', 'sort_order' => 1],
                    ['name' => 'DG Backup', 'code' => 'DG_BACKUP', 'sort_order' => 2],
                ],
            ],
            'Salary' => [
                'code' => 'SALARY',
                'sort_order' => 2,
                'subcategories' => [
                    ['name' => 'Staff Salary', 'code' => 'STAFF_SALARY', 'sort_order' => 1],
                    ['name' => 'Consultant Payout', 'code' => 'CONSULTANT_PAYOUT', 'sort_order' => 2],
                ],
            ],
            'Ads' => [
                'code' => 'ADS',
                'sort_order' => 3,
                'subcategories' => [
                    ['name' => 'Meta Ads', 'code' => 'META_ADS', 'sort_order' => 1],
                    ['name' => 'Google Ads', 'code' => 'GOOGLE_ADS', 'sort_order' => 2],
                ],
            ],
            'Travel' => [
                'code' => 'TRAVEL',
                'sort_order' => 4,
                'subcategories' => [
                    ['name' => 'Fuel', 'code' => 'FUEL', 'sort_order' => 1],
                    ['name' => 'Hotel', 'code' => 'HOTEL', 'sort_order' => 2],
                    ['name' => 'Taxi', 'code' => 'TAXI', 'sort_order' => 3],
                ],
            ],
        ];

        foreach ($categoryMap as $categoryName => $categoryData) {
            $category = ExpenseCategory::updateOrCreate(
                ['code' => $categoryData['code']],
                [
                    'name' => $categoryName,
                    'sort_order' => $categoryData['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($categoryData['subcategories'] as $subcategoryData) {
                ExpenseSubcategory::updateOrCreate(
                    ['code' => $subcategoryData['code']],
                    [
                        'expense_category_id' => $category->id,
                        'name' => $subcategoryData['name'],
                        'sort_order' => $subcategoryData['sort_order'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
