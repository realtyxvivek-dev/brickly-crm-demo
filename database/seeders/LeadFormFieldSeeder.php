<?php

namespace Database\Seeders;

use App\Models\LeadFormField;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LeadFormFieldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $fields = [
            [
                'field_key' => 'category',
                'field_label' => 'Category',
                'field_type' => 'select',
                'field_level' => 'sales_executive',
                'options' => ['Residential', 'Commercial', 'Both', 'N.A'],
                'is_required' => true,
                'display_order' => 1,
                'placeholder' => 'Select Category',
                'help_text' => 'Select the property category',
            ],
            [
                'field_key' => 'preferred_location',
                'field_label' => 'Preferred Location',
                'field_type' => 'select',
                'field_level' => 'sales_executive',
                'options' => [
                    'Inside City',
                    'Sitapur Road',
                    'Hardoi Road',
                    'Faizabad Road',
                    'Sultanpur Road',
                    'Shaheed Path',
                    'Raebareily Road',
                    'Kanpur Road',
                    'Outer Ring Road',
                    'Bijnor Road',
                    'Deva Road',
                    'Sushant Golf City',
                    'Vrindavan Yojana',
                    'N.A',
                ],
                'is_required' => true,
                'display_order' => 2,
                'placeholder' => 'Select Preferred Location',
                'help_text' => 'Select the preferred location',
            ],
            [
                'field_key' => 'type',
                'field_label' => 'Type',
                'field_type' => 'select',
                'field_level' => 'sales_executive',
                'options' => ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A'], // Default options
                'is_required' => true,
                'dependent_field' => 'category',
                'dependent_conditions' => [
                    'Residential' => ['Plots & Villas', 'Apartments', 'Studio', 'Farmhouse', 'N.A'],
                    'Commercial' => ['Retail Shops', 'Office Space', 'Studio', 'N.A'],
                    'Both' => ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A'],
                    'N.A' => ['N.A'],
                ],
                'display_order' => 3,
                'placeholder' => 'Select Type (select category first)',
                'help_text' => 'Select property type (depends on category selection)',
            ],
            [
                'field_key' => 'purpose',
                'field_label' => 'Purpose',
                'field_type' => 'select',
                'field_level' => 'sales_executive',
                'options' => [
                    'End Use',
                    'Short Term Investment',
                    'Long Term Investment',
                    'Rental Income',
                    'Investment + End Use',
                    'N.A',
                ],
                'is_required' => true,
                'display_order' => 4,
                'placeholder' => 'Select Purpose',
                'help_text' => 'Select the purpose for property',
            ],
            [
                'field_key' => 'possession',
                'field_label' => 'Possession',
                'field_type' => 'select',
                'field_level' => 'sales_executive',
                'options' => [
                    'Under Construction',
                    'Ready To Move',
                    'Pre Launch',
                    'Both',
                    'N.A',
                ],
                'is_required' => true,
                'display_order' => 5,
                'placeholder' => 'Select Possession',
                'help_text' => 'Select possession status',
            ],
            [
                'field_key' => 'budget',
                'field_label' => 'Budget',
                'field_type' => 'select',
                'field_level' => 'sales_executive',
                'options' => [
                    'Below 50 Lacs',
                    '50-75 Lacs',
                    '75 Lacs-1 Cr',
                    'Above 1 Cr',
                    'Above 2 Cr',
                    'N.A',
                ],
                'is_required' => true,
                'display_order' => 6,
                'placeholder' => 'Select Budget',
                'help_text' => 'Select budget range',
            ],
            [
                'field_key' => 'final_status',
                'field_label' => 'Final Status',
                'field_type' => 'select',
                'field_level' => 'sales_executive',
                'options' => [
                    'Dead',
                    'Follow Up',
                    'Closed',
                    'N.A',
                    'Prospect',
                    'Elsewhere',
                ],
                'is_required' => true,
                'display_order' => 7,
                'placeholder' => 'Select Final Status',
                'help_text' => 'Select the final status of the lead',
            ],
            [
                'field_key' => 'follow_up_date',
                'field_label' => 'Follow-up Date',
                'field_type' => 'date',
                'field_level' => 'sales_executive',
                'is_required' => false,
                'dependent_field' => 'final_status',
                'dependent_conditions' => [
                    'show_when' => ['Follow Up'],
                ],
                'display_order' => 8,
                'placeholder' => 'Select Follow-up Date',
                'help_text' => 'Select date for follow-up (required if status is Follow Up)',
            ],
            [
                'field_key' => 'follow_up_time',
                'field_label' => 'Follow-up Time',
                'field_type' => 'time',
                'field_level' => 'sales_executive',
                'is_required' => false,
                'dependent_field' => 'final_status',
                'dependent_conditions' => [
                    'show_when' => ['Follow Up'],
                ],
                'display_order' => 9,
                'placeholder' => 'Select Follow-up Time',
                'help_text' => 'Select time for follow-up (required if status is Follow Up)',
            ],
        ];

        foreach ($fields as $field) {
            LeadFormField::updateOrCreate(
                ['field_key' => $field['field_key']],
                $field
            );
        }

        $this->command->info('Default lead form fields seeded successfully!');
    }
}
