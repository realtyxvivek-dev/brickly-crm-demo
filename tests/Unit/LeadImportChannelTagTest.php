<?php

namespace Tests\Unit;

use App\Models\ImportBatch;
use App\Models\ImportedLead;
use App\Models\Lead;
use Tests\TestCase;

class LeadImportChannelTagTest extends TestCase
{
    public function test_simple_import_lead_gets_lsq_tag(): void
    {
        $lead = new Lead(['source' => 'sheet']);
        $lead->setRelation('latestImportedLead', new ImportedLead([
            'import_data' => ['kind' => 'simple_import'],
        ]));

        $this->assertTrue($lead->isSimpleUploadLead());
        $this->assertSame('LSQ', $lead->import_channel_tag);
    }

    public function test_non_simple_import_lead_does_not_get_lsq_tag(): void
    {
        $lead = new Lead(['source' => 'sheet']);
        $importedLead = new ImportedLead([
            'import_data' => ['kind' => 'old_crm'],
        ]);
        $importedLead->setRelation('importBatch', new ImportBatch([
            'import_kind' => 'old_crm',
        ]));
        $lead->setRelation('latestImportedLead', $importedLead);

        $this->assertFalse($lead->isSimpleUploadLead());
        $this->assertNull($lead->import_channel_tag);
    }
}
