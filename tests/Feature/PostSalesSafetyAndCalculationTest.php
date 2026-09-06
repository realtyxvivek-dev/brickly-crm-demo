<?php

namespace Tests\Feature;

use App\Models\PostSaleCase;
use App\Services\PostSalesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostSalesSafetyAndCalculationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->createSchema();
    }

    public function test_customer_email_delivery_is_disabled_and_test_recipient_is_fixed(): void
    {
        Mail::fake();
        Config::set('post_sales.customer_mail_enabled', false);

        $this->artisan('post-sales:send-demand-reminders')
            ->expectsOutput('Customer delivery is disabled. No email sent.')
            ->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame('realtyxvivek@gmail.com', config('post_sales.test_recipient'));
    }

    public function test_verified_collection_refund_and_builder_slab_calculations_are_correct(): void
    {
        $case = PostSaleCase::create([
            'site_visit_id' => 1, 'case_number' => 'PS-TEST-1', 'customer_name' => 'Test Customer',
            'agreement_value' => 1000000, 'revenue_value' => 100000, 'status' => 'active',
        ]);
        $case->transactions()->create(['type' => 'payment', 'amount' => 400000, 'transaction_date' => '2026-09-01', 'status' => 'verified']);
        $case->transactions()->create(['type' => 'refund', 'amount' => 50000, 'transaction_date' => '2026-09-02', 'status' => 'verified']);
        $case->transactions()->create(['type' => 'payment', 'amount' => 999999, 'transaction_date' => '2026-09-02', 'status' => 'pending']);
        $case->slabs()->createMany([
            ['customer_collection_percent' => 20, 'brokerage_release_percent' => 33, 'sort_order' => 1],
            ['customer_collection_percent' => 35, 'brokerage_release_percent' => 66, 'sort_order' => 2],
            ['customer_collection_percent' => 50, 'brokerage_release_percent' => 100, 'sort_order' => 3],
        ]);
        $claim = $case->claims()->create([
            'collection_percent' => 35, 'release_percent' => 66, 'eligible_amount' => 66000,
            'claim_amount' => 50000, 'claim_date' => '2026-09-02', 'status' => 'claim_raised',
        ]);
        $claim->receipts()->create(['amount' => 20000, 'received_date' => '2026-09-02']);

        $metrics = app(PostSalesService::class)->metrics($case);

        $this->assertSame(350000.0, $metrics['net_collected']);
        $this->assertSame(35.0, $metrics['collection_percent']);
        $this->assertSame(66.0, $metrics['release_percent']);
        $this->assertSame(66000.0, $metrics['eligible_brokerage']);
        $this->assertSame(16000.0, $metrics['fresh_claimable']);
        $this->assertSame(20000.0, $metrics['received']);
        $this->assertSame(30000.0, $metrics['outstanding']);
        $this->assertFalse($metrics['over_claimed']);
    }

    private function createSchema(): void
    {
        Schema::create('post_sale_cases', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('site_visit_id'); $table->string('case_number');
            $table->string('customer_name'); $table->decimal('agreement_value', 15, 2)->default(0);
            $table->decimal('revenue_value', 15, 2)->default(0); $table->string('status');
            $table->timestamps(); $table->softDeletes();
        });
        Schema::create('post_sale_transactions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('post_sale_case_id'); $table->unsignedBigInteger('demand_id')->nullable();
            $table->string('type'); $table->decimal('amount', 15, 2); $table->date('transaction_date');
            $table->string('status'); $table->timestamps();
        });
        Schema::create('post_sale_case_slabs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('post_sale_case_id'); $table->unsignedBigInteger('source_scheme_id')->nullable();
            $table->decimal('customer_collection_percent', 7, 3); $table->decimal('brokerage_release_percent', 7, 3);
            $table->unsignedInteger('sort_order')->default(0); $table->timestamps();
        });
        Schema::create('builder_claims', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('post_sale_case_id'); $table->decimal('collection_percent', 7, 3);
            $table->decimal('release_percent', 7, 3); $table->decimal('eligible_amount', 15, 2);
            $table->decimal('claim_amount', 15, 2); $table->decimal('gst_amount', 15, 2)->default(0);
            $table->decimal('tds_amount', 15, 2)->default(0); $table->date('claim_date'); $table->date('expected_date')->nullable();
            $table->string('status'); $table->timestamps();
        });
        Schema::create('builder_receipts', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('builder_claim_id'); $table->decimal('amount', 15, 2);
            $table->date('received_date'); $table->timestamps();
        });
    }
}
