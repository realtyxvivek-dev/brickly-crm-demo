<?php

namespace App\Services;

use App\Models\Lead;

class FormDetectionService
{
    /**
     * Get field definitions for known form types
     */
    public function getFieldDefinitions(string $formType, string $locationPath = ''): array
    {
        $definitions = [];

        if ($locationPath === 'lead-detail.meeting') {
            return $this->getLeadDetailMeetingFormFields();
        }

        if ($locationPath === 'lead-detail.requirements') {
            return $this->getLeadDetailRequirementsFormFields();
        }

        if ($locationPath === 'lead-detail.site-visit') {
            return $this->getLeadDetailSiteVisitFormFields();
        }

        if ($locationPath === 'lead-detail.follow-up') {
            return $this->getLeadDetailFollowUpFormFields();
        }

        switch ($formType) {
            case 'meeting':
                $definitions = $this->getMeetingFormFields();
                break;
            case 'lead':
                $definitions = $this->getLeadFormFields();
                break;
            case 'prospect':
                // Check if it's the prospect details form from tasks
                if (str_contains($locationPath, 'prospect-details')) {
                    $definitions = $this->getProspectDetailsFormFields();
                } else {
                    $definitions = $this->getProspectFormFields();
                }
                break;
            case 'site_visit':
                $definitions = $this->getSiteVisitFormFields();
                break;
            case 'follow_up':
                $definitions = $this->getLeadDetailFollowUpFormFields();
                break;
            default:
                // Try to detect from location path
                $definitions = $this->detectFieldsFromLocationPath($locationPath);
        }

        return $definitions;
    }

    /**
     * Get meeting form field definitions
     */
    protected function getMeetingFormFields(): array
    {
        return [
            [
                'field_key' => 'customer_name',
                'field_type' => 'text',
                'label' => 'Customer Name',
                'placeholder' => 'Enter customer name',
                'required' => true,
                'order' => 0,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'phone',
                'field_type' => 'text',
                'label' => 'Phone',
                'placeholder' => 'Enter phone number',
                'required' => true,
                'order' => 1,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'employee',
                'field_type' => 'text',
                'label' => 'Employee',
                'placeholder' => 'Enter employee name',
                'required' => false,
                'order' => 2,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'occupation',
                'field_type' => 'text',
                'label' => 'Occupation',
                'placeholder' => 'e.g. IT / Business',
                'required' => false,
                'order' => 3,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'date_of_visit',
                'field_type' => 'date',
                'label' => 'Date of Visit',
                'placeholder' => 'Select date',
                'required' => true,
                'order' => 4,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'time',
                'field_type' => 'text',
                'label' => 'Time',
                'placeholder' => 'Enter meeting time',
                'required' => false,
                'order' => 5,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'address',
                'field_type' => 'textarea',
                'label' => 'Address',
                'placeholder' => 'Enter meeting address',
                'required' => false,
                'order' => 6,
                'section' => 'Additional Information',
            ],
            [
                'field_key' => 'notes',
                'field_type' => 'textarea',
                'label' => 'Notes',
                'placeholder' => 'Additional notes',
                'required' => false,
                'order' => 7,
                'section' => 'Additional Information',
            ],
        ];
    }

    protected function getLeadDetailMeetingFormFields(): array
    {
        return [
            [
                'field_key' => 'meeting_type',
                'field_type' => 'select',
                'label' => 'Meeting type',
                'required' => true,
                'options' => ['Initial Meeting', 'Follow-up Meeting', 'Negotiation Meeting', 'Closing Meeting'],
                'order' => 0,
                'section' => 'Lead Detail Meeting Popup',
            ],
            [
                'field_key' => 'meeting_date',
                'field_type' => 'date',
                'label' => 'Scheduled date',
                'required' => true,
                'order' => 1,
                'section' => 'Lead Detail Meeting Popup',
            ],
            [
                'field_key' => 'meeting_time',
                'field_type' => 'time',
                'label' => 'Scheduled time',
                'required' => true,
                'order' => 2,
                'section' => 'Lead Detail Meeting Popup',
            ],
            [
                'field_key' => 'meeting_mode',
                'field_type' => 'select',
                'label' => 'Meeting mode',
                'required' => true,
                'options' => ['Online', 'Offline'],
                'order' => 3,
                'section' => 'Lead Detail Meeting Popup',
            ],
            [
                'field_key' => 'meeting_link',
                'field_type' => 'url',
                'label' => 'Meeting link',
                'required' => false,
                'order' => 4,
                'section' => 'Lead Detail Meeting Popup',
            ],
            [
                'field_key' => 'location',
                'field_type' => 'text',
                'label' => 'Location',
                'required' => false,
                'order' => 5,
                'section' => 'Lead Detail Meeting Popup',
            ],
            [
                'field_key' => 'meeting_notes',
                'field_type' => 'textarea',
                'label' => 'Remark',
                'required' => false,
                'order' => 6,
                'section' => 'Lead Detail Meeting Popup',
            ],
            [
                'field_key' => 'reminder_enabled',
                'field_type' => 'checkbox',
                'label' => 'Remind me before meeting',
                'required' => false,
                'order' => 7,
                'section' => 'Lead Detail Meeting Popup',
            ],
        ];
    }

    protected function getLeadDetailRequirementsFormFields(): array
    {
        return [
            [
                'field_key' => 'name',
                'field_type' => 'text',
                'label' => 'Customer name',
                'placeholder' => 'Enter lead name',
                'required' => true,
                'order' => 0,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'phone',
                'field_type' => 'text',
                'label' => 'Phone',
                'placeholder' => 'Enter phone number',
                'required' => true,
                'order' => 1,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'category',
                'field_type' => 'select',
                'label' => 'Category',
                'required' => true,
                'options' => ['Residential', 'Commercial', 'Both', 'N.A'],
                'order' => 2,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'preferred_location',
                'field_type' => 'select',
                'label' => 'Location',
                'required' => true,
                'options' => ['Inside City', 'Sitapur Road', 'Hardoi Road', 'Faizabad Road', 'Sultanpur Road', 'Shaheed Path', 'Raebareily Road', 'Kanpur Road', 'Outer Ring Road', 'Bijnor Road', 'Deva Road', 'Sushant Golf City', 'Vrindavan Yojana', 'N.A'],
                'order' => 3,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'budget',
                'field_type' => 'select',
                'label' => 'Budget',
                'required' => true,
                'options' => ['Below 50 Lacs', '50-75 Lacs', '75 Lacs-1 Cr', 'Above 1 Cr', 'Above 2 Cr', 'N.A'],
                'order' => 4,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'type',
                'field_type' => 'select',
                'label' => 'Type',
                'required' => true,
                'options' => ['Plots & Villas', 'Apartments', 'Retail Shops', 'Office Space', 'Studio', 'Farmhouse', 'Agricultural', 'Others', 'N.A'],
                'order' => 5,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'purpose',
                'field_type' => 'select',
                'label' => 'Purpose',
                'required' => true,
                'options' => ['End Use', 'Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use', 'N.A'],
                'order' => 6,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'possession',
                'field_type' => 'select',
                'label' => 'Possession',
                'required' => true,
                'options' => ['Under Construction', 'Ready To Move', 'Pre Launch', 'Both', 'N.A'],
                'order' => 7,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'lead_status',
                'field_type' => 'select',
                'label' => 'Status',
                'required' => true,
                'options' => ['hot', 'warm', 'cold', 'junk'],
                'order' => 8,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'lead_quality',
                'field_type' => 'select',
                'label' => 'Lead quality',
                'required' => true,
                'options' => ['1', '2', '3', '4', '5'],
                'order' => 9,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'interested_projects',
                'field_type' => 'select',
                'label' => 'Interested projects',
                'placeholder' => 'Search and select interested projects',
                'required' => true,
                'order' => 10,
                'section' => 'Lead Detail Requirements',
                'options' => ['Jash Elevate', 'Oro Constella', 'Okas Res.', 'Other'],
            ],
            [
                'field_key' => 'customer_job',
                'field_type' => 'text',
                'label' => 'Customer job',
                'placeholder' => 'Enter job / occupation',
                'required' => false,
                'order' => 11,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'industry_sector',
                'field_type' => 'select',
                'label' => 'Industry / sector',
                'required' => false,
                'options' => ['IT', 'Education', 'Healthcare', 'Business', 'FMCG', 'Government', 'Other'],
                'order' => 12,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'buying_frequency',
                'field_type' => 'select',
                'label' => 'Buying frequency',
                'required' => false,
                'options' => ['Regular', 'Occasional', 'First-time'],
                'order' => 13,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'living_city',
                'field_type' => 'text',
                'label' => 'Living city',
                'placeholder' => 'Enter living city',
                'required' => false,
                'order' => 14,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'city_type',
                'field_type' => 'select',
                'label' => 'City type',
                'required' => false,
                'options' => ['Metro', 'Tier 1', 'Tier 2', 'Tier 3', 'Local Resident'],
                'order' => 15,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'manager_remark',
                'field_type' => 'textarea',
                'label' => 'Remark',
                'placeholder' => 'Enter remarks or notes...',
                'required' => false,
                'order' => 16,
                'section' => 'Lead Detail Requirements',
            ],
            [
                'field_key' => 'meta_stage',
                'field_type' => 'select',
                'label' => 'Meta stage',
                'required' => false,
                'options' => ['intake', 'qualified', 'in_progress', 'converted', 'lost', 'not_qualified'],
                'order' => 17,
                'section' => 'Meta Review',
            ],
            [
                'field_key' => 'meta_review_note',
                'field_type' => 'textarea',
                'label' => 'Meta review note',
                'placeholder' => 'Add Meta qualification note...',
                'required' => false,
                'order' => 18,
                'section' => 'Meta Review',
            ],
        ];
    }

    protected function getLeadDetailSiteVisitFormFields(): array
    {
        return [
            [
                'field_key' => 'visit_date',
                'field_type' => 'date',
                'label' => 'Visit date',
                'required' => true,
                'order' => 0,
                'section' => 'Lead Detail Site Visit Popup',
            ],
            [
                'field_key' => 'visit_time',
                'field_type' => 'time',
                'label' => 'Visit time',
                'required' => true,
                'order' => 1,
                'section' => 'Lead Detail Site Visit Popup',
            ],
            [
                'field_key' => 'visit_type',
                'field_type' => 'select',
                'label' => 'Visit type',
                'required' => false,
                'options' => ['Site visit', 'Office visit'],
                'order' => 2,
                'section' => 'Lead Detail Site Visit Popup',
            ],
            [
                'field_key' => 'project_name',
                'field_type' => 'text',
                'label' => 'Project to visit',
                'required' => false,
                'order' => 3,
                'section' => 'Lead Detail Site Visit Popup',
            ],
            [
                'field_key' => 'visit_location',
                'field_type' => 'text',
                'label' => 'Visit location',
                'required' => false,
                'order' => 4,
                'section' => 'Lead Detail Site Visit Popup',
            ],
            [
                'field_key' => 'visit_notes',
                'field_type' => 'textarea',
                'label' => 'Remark',
                'required' => false,
                'order' => 5,
                'section' => 'Lead Detail Site Visit Popup',
            ],
            [
                'field_key' => 'visit_reminder',
                'field_type' => 'checkbox',
                'label' => 'Remind me before visit',
                'required' => false,
                'order' => 6,
                'section' => 'Lead Detail Site Visit Popup',
            ],
        ];
    }

    protected function getLeadDetailFollowUpFormFields(): array
    {
        return [
            [
                'field_key' => 'followup_required',
                'field_type' => 'checkbox',
                'label' => 'Follow up required',
                'required' => false,
                'order' => 0,
                'section' => 'Lead Detail Follow Up Popup',
            ],
            [
                'field_key' => 'scheduled_at',
                'field_type' => 'datetime-local',
                'label' => 'Follow up date & time',
                'required' => true,
                'order' => 1,
                'section' => 'Lead Detail Follow Up Popup',
            ],
            [
                'field_key' => 'notes',
                'field_type' => 'textarea',
                'label' => 'Remark',
                'required' => false,
                'order' => 2,
                'section' => 'Lead Detail Follow Up Popup',
            ],
        ];
    }

    /**
     * Get lead form field definitions
     */
    protected function getLeadFormFields(): array
    {
        return [
            [
                'field_key' => 'name',
                'field_type' => 'text',
                'label' => 'Name',
                'placeholder' => 'Enter lead name',
                'required' => true,
                'order' => 0,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'phone',
                'field_type' => 'text',
                'label' => 'Phone',
                'placeholder' => 'Enter phone number',
                'required' => true,
                'order' => 1,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'email',
                'field_type' => 'email',
                'label' => 'Email',
                'placeholder' => 'Enter email address',
                'required' => false,
                'order' => 2,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'address',
                'field_type' => 'textarea',
                'label' => 'Address',
                'placeholder' => 'Enter full address',
                'required' => false,
                'order' => 4,
                'section' => 'Location Details',
            ],
            [
                'field_key' => 'city',
                'field_type' => 'text',
                'label' => 'City',
                'placeholder' => 'Enter city',
                'required' => false,
                'order' => 5,
                'section' => 'Location Details',
            ],
            [
                'field_key' => 'state',
                'field_type' => 'text',
                'label' => 'State',
                'placeholder' => 'Enter state',
                'required' => false,
                'order' => 6,
                'section' => 'Location Details',
            ],
            [
                'field_key' => 'pincode',
                'field_type' => 'text',
                'label' => 'Pincode',
                'placeholder' => 'Enter pincode',
                'required' => false,
                'order' => 7,
                'section' => 'Location Details',
            ],
            [
                'field_key' => 'preferred_location',
                'field_type' => 'select',
                'label' => 'Preferred Location',
                'placeholder' => 'Select location',
                'required' => false,
                'order' => 8,
                'section' => 'Property Preferences',
                'options' => ['Shaheed Path', 'Sultanpur Road', 'Kanpur Road', 'Bijnore Road', 'IIM Road', 'Faizabad Road', 'Outer Ring Road', 'Sushant Golf City', 'Other'],
            ],
            [
                'field_key' => 'preferred_size',
                'field_type' => 'text',
                'label' => 'Preferred Size',
                'placeholder' => 'e.g., 2 BHK, 1200 sqft',
                'required' => false,
                'order' => 9,
                'section' => 'Property Preferences',
            ],
            [
                'field_key' => 'preferred_projects',
                'field_type' => 'select',
                'label' => 'Preferred Projects',
                'placeholder' => 'Select projects',
                'required' => false,
                'order' => 10,
                'section' => 'Property Preferences',
            ],
            [
                'field_key' => 'budget',
                'field_type' => 'select',
                'label' => 'Budget',
                'placeholder' => 'Select budget',
                'required' => false,
                'order' => 10,
                'section' => 'Property Preferences',
                'options' => ['Under ₹1 Cr', '₹1.1 Cr – ₹2 Cr', 'Above ₹2 Cr'],
            ],
            [
                'field_key' => 'source',
                'field_type' => 'select',
                'label' => 'Source',
                'placeholder' => 'Select source',
                'required' => false,
                'order' => 3,
                'section' => 'Basic Information',
                'options' => array_values(Lead::sourceOptions()),
            ],
            [
                'field_key' => 'use_end_use',
                'field_type' => 'select',
                'label' => 'Use/End Use',
                'placeholder' => 'Select use/end use',
                'required' => false,
                'order' => 12,
                'section' => 'Property Preferences',
                'options' => ['End User', '2nd Investments'],
            ],
            [
                'field_key' => 'property_type',
                'field_type' => 'select',
                'label' => 'Property Type',
                'placeholder' => 'Select property type',
                'required' => false,
                'order' => 13,
                'section' => 'Property Preferences',
                'options' => ['Apartment', 'Villa', 'Plot', 'Commercial', 'Other'],
            ],
            [
                'field_key' => 'possession_status',
                'field_type' => 'select',
                'label' => 'Possession Status',
                'placeholder' => 'Select possession status',
                'required' => false,
                'order' => 14,
                'section' => 'Property Preferences',
                'options' => ['Ready to Move', 'Under Construction'],
            ],
            [
                'field_key' => 'assigned_to',
                'field_type' => 'select',
                'label' => 'Assign To User',
                'placeholder' => 'Select user',
                'required' => false,
                'order' => 15,
                'section' => 'Assignment',
            ],
            [
                'field_key' => 'requirements',
                'field_type' => 'textarea',
                'label' => 'Requirements',
                'placeholder' => 'Additional requirements or preferences',
                'required' => false,
                'order' => 16,
                'section' => 'Additional Information',
            ],
            [
                'field_key' => 'notes',
                'field_type' => 'textarea',
                'label' => 'Notes',
                'placeholder' => 'Any additional notes',
                'required' => false,
                'order' => 17,
                'section' => 'Additional Information',
            ],
        ];
    }

    /**
     * Get prospect form field definitions
     */
    protected function getProspectFormFields(): array
    {
        return [
            [
                'field_key' => 'customer_name',
                'field_type' => 'text',
                'label' => 'Customer Name',
                'placeholder' => 'Enter customer name',
                'required' => true,
                'order' => 0,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'phone',
                'field_type' => 'text',
                'label' => 'Phone',
                'placeholder' => 'Enter phone number',
                'required' => true,
                'order' => 1,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'budget',
                'field_type' => 'number',
                'label' => 'Budget',
                'placeholder' => 'Enter budget',
                'required' => false,
                'order' => 2,
                'section' => 'Property Preferences',
            ],
            [
                'field_key' => 'preferred_location',
                'field_type' => 'text',
                'label' => 'Preferred Location',
                'placeholder' => 'Enter preferred location',
                'required' => false,
                'order' => 3,
                'section' => 'Property Preferences',
            ],
            [
                'field_key' => 'size',
                'field_type' => 'text',
                'label' => 'Size',
                'placeholder' => 'e.g., 2 BHK, 1200 sqft',
                'required' => false,
                'order' => 4,
                'section' => 'Property Preferences',
            ],
            [
                'field_key' => 'purpose',
                'field_type' => 'select',
                'label' => 'Purpose',
                'placeholder' => 'Select purpose',
                'required' => false,
                'options' => ['end_user', 'investment'],
                'order' => 5,
                'section' => 'Property Preferences',
            ],
        ];
    }

    /**
     * Get prospect details form field definitions (Senior Manager Tasks)
     */
    protected function getProspectDetailsFormFields(): array
    {
        return [
            [
                'field_key' => 'customer_name',
                'field_type' => 'text',
                'label' => 'Customer Name',
                'placeholder' => 'Enter customer name',
                'required' => true,
                'order' => 0,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'phone',
                'field_type' => 'text',
                'label' => 'Phone Number',
                'placeholder' => 'Enter phone number',
                'required' => true,
                'order' => 1,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'email',
                'field_type' => 'email',
                'label' => 'Email',
                'placeholder' => 'Enter email address',
                'required' => false,
                'order' => 2,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'address',
                'field_type' => 'textarea',
                'label' => 'Address',
                'placeholder' => 'Enter full address',
                'required' => false,
                'order' => 3,
                'section' => 'Address Information',
            ],
            [
                'field_key' => 'city',
                'field_type' => 'text',
                'label' => 'City',
                'placeholder' => 'Enter city',
                'required' => false,
                'order' => 4,
                'section' => 'Address Information',
            ],
            [
                'field_key' => 'state',
                'field_type' => 'text',
                'label' => 'State',
                'placeholder' => 'Enter state',
                'required' => false,
                'order' => 5,
                'section' => 'Address Information',
            ],
            [
                'field_key' => 'pincode',
                'field_type' => 'text',
                'label' => 'Pincode',
                'placeholder' => 'Enter pincode',
                'required' => false,
                'order' => 6,
                'section' => 'Address Information',
            ],
            [
                'field_key' => 'budget',
                'field_type' => 'number',
                'label' => 'Budget',
                'placeholder' => 'Enter budget',
                'required' => false,
                'order' => 7,
                'section' => 'Property Preferences',
            ],
            [
                'field_key' => 'preferred_location',
                'field_type' => 'text',
                'label' => 'Preferred Location',
                'placeholder' => 'e.g., South Mumbai, Bandra',
                'required' => false,
                'order' => 8,
                'section' => 'Property Preferences',
            ],
            [
                'field_key' => 'size',
                'field_type' => 'text',
                'label' => 'Size',
                'placeholder' => 'e.g., 2 BHK, 1200 sqft',
                'required' => false,
                'order' => 9,
                'section' => 'Property Preferences',
            ],
            [
                'field_key' => 'purpose',
                'field_type' => 'select',
                'label' => 'Purpose',
                'placeholder' => 'Select purpose',
                'required' => false,
                'options' => ['End User', 'Investment'],
                'order' => 10,
                'section' => 'Property Preferences',
            ],
            [
                'field_key' => 'possession',
                'field_type' => 'text',
                'label' => 'Possession',
                'placeholder' => 'Enter possession details',
                'required' => false,
                'order' => 11,
                'section' => 'Property Preferences',
            ],
            [
                'field_key' => 'lead_status',
                'field_type' => 'select',
                'label' => 'Lead Status',
                'placeholder' => 'Select lead status',
                'required' => true,
                'options' => ['Hot', 'Warm', 'Cold', 'Junk'],
                'order' => 12,
                'section' => 'Verification',
            ],
            [
                'field_key' => 'manager_remark',
                'field_type' => 'textarea',
                'label' => 'Manager Remark',
                'placeholder' => 'Enter remarks or notes...',
                'required' => false,
                'order' => 13,
                'section' => 'Verification',
            ],
        ];
    }

    /**
     * Get site visit form field definitions
     */
    protected function getSiteVisitFormFields(): array
    {
        return [
            [
                'field_key' => 'customer_name',
                'field_type' => 'text',
                'label' => 'Customer Name',
                'placeholder' => 'Enter customer name',
                'required' => true,
                'order' => 0,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'phone',
                'field_type' => 'text',
                'label' => 'Phone',
                'placeholder' => 'Enter phone number',
                'required' => true,
                'order' => 1,
                'section' => 'Basic Information',
            ],
            [
                'field_key' => 'visit_date',
                'field_type' => 'date',
                'label' => 'Visit Date',
                'placeholder' => 'Select visit date',
                'required' => true,
                'order' => 2,
                'section' => 'Visit Details',
            ],
            [
                'field_key' => 'visit_time',
                'field_type' => 'text',
                'label' => 'Visit Time',
                'placeholder' => 'Enter visit time',
                'required' => false,
                'order' => 3,
                'section' => 'Visit Details',
            ],
            [
                'field_key' => 'property_address',
                'field_type' => 'textarea',
                'label' => 'Property Address',
                'placeholder' => 'Enter property address',
                'required' => false,
                'order' => 4,
                'section' => 'Visit Details',
            ],
            [
                'field_key' => 'notes',
                'field_type' => 'textarea',
                'label' => 'Notes',
                'placeholder' => 'Additional notes',
                'required' => false,
                'order' => 5,
                'section' => 'Additional Information',
            ],
        ];
    }

    /**
     * Detect fields from location path
     */
    protected function detectFieldsFromLocationPath(string $locationPath): array
    {
        // Try to match location path to form type
        if (str_contains($locationPath, 'meeting')) {
            return $this->getMeetingFormFields();
        }
        if (str_contains($locationPath, 'lead')) {
            return $this->getLeadFormFields();
        }
        if (str_contains($locationPath, 'prospect-details')) {
            return $this->getProspectDetailsFormFields();
        }
        if (str_contains($locationPath, 'prospect')) {
            return $this->getProspectFormFields();
        }
        if (str_contains($locationPath, 'site-visit') || str_contains($locationPath, 'site_visit')) {
            return $this->getSiteVisitFormFields();
        }

        return [];
    }
}
