<?php

namespace Tests\Feature;

use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadDynamicSourceOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
        Lead::forgetResolvedSourceOptions();
    }

    protected function tearDown(): void
    {
        Lead::forgetResolvedSourceOptions();
        parent::tearDown();
    }

    public function test_source_options_follow_published_new_lead_add_form(): void
    {
        $form = DynamicForm::create([
            'name' => 'Add Lead Form',
            'slug' => 'add-lead-form',
            'location_path' => 'leads.create',
            'form_type' => 'lead',
            'status' => 'published',
            'is_active' => true,
        ]);

        DynamicFormField::create([
            'form_id' => $form->id,
            'field_key' => 'source',
            'field_type' => 'select',
            'label' => 'Source',
            'options' => ['Meta', 'Reference', 'Organic'],
            'required' => false,
            'order' => 1,
            'section' => 'default',
        ]);

        $this->assertSame([
            'meta' => 'Meta',
            'reference' => 'Reference',
            'organic' => 'Organic',
            'meta_awareness' => 'Meta Awareness',
        ], Lead::sourceOptions());

        $this->assertSame('organic', Lead::normalizeSource('Organic'));
        $this->assertSame('Organic', Lead::displaySourceLabel('organic'));
    }

    public function test_dynamic_custom_source_option_normalizes_to_canonical_database_value(): void
    {
        $form = DynamicForm::create([
            'name' => 'Add Lead Form',
            'slug' => 'add-lead-form',
            'location_path' => 'leads.create',
            'form_type' => 'lead',
            'status' => 'published',
            'is_active' => true,
        ]);

        DynamicFormField::create([
            'form_id' => $form->id,
            'field_key' => 'source',
            'field_type' => 'select',
            'label' => 'Source',
            'options' => ['07 (Call)', 'Walk In'],
            'required' => false,
            'order' => 1,
            'section' => 'default',
        ]);

        $this->assertSame('07_call', array_key_first(Lead::sourceOptions()));
        $this->assertSame('ivr', Lead::normalizeSource('07_call'));
        $this->assertSame('ivr', Lead::normalizeSource('07 (Call)'));
        $this->assertSame('other', Lead::normalizeSource('Walk In'));

        $lead = new Lead([
            'source' => '07_call',
        ]);

        $this->assertSame('ivr', $lead->source);
    }

    public function test_display_source_label_humanizes_legacy_or_unconfigured_saved_source(): void
    {
        $this->assertSame('Organic', Lead::displaySourceLabel('organic'));
        $this->assertSame('Walk In', Lead::displaySourceLabel('walk_in'));
    }

    public function test_source_options_follow_active_lead_source_master_when_configured(): void
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('type')->default('other');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        LeadSource::create([
            'name' => 'MagicBricks',
            'key' => 'magicbricks',
            'type' => 'portal',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        LeadSource::create([
            'name' => 'Inactive Source',
            'key' => 'inactive_source',
            'type' => 'other',
            'is_active' => false,
            'sort_order' => 20,
        ]);

        Lead::forgetResolvedSourceOptions();

        $this->assertSame(['magicbricks' => 'MagicBricks'], Lead::sourceOptions());
        $this->assertSame('magicbricks', Lead::normalizeSource('MagicBricks'));
        $this->assertSame('MagicBricks', Lead::displaySourceLabel('magicbricks'));

        $lead = new Lead(['source' => 'MagicBricks']);

        $this->assertSame('magicbricks', $lead->source);
    }

    private function createSchema(): void
    {
        Schema::create('dynamic_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('location_path');
            $table->string('form_type')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('replaces_form_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('dynamic_form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id');
            $table->string('field_key');
            $table->string('field_type')->default('text');
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->boolean('required')->default(false);
            $table->integer('order')->default(0);
            $table->string('section')->nullable();
            $table->json('styles')->nullable();
            $table->text('default_value')->nullable();
            $table->timestamps();
        });
    }
}
