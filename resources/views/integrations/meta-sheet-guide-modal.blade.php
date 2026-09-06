{{-- Full connection guide modal: Meta/Facebook + CRM steps (plain English, non-coder friendly) --}}
<div id="metaSheetGuideModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-labelledby="metaSheetGuideModalTitle" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="document.getElementById('metaSheetGuideModal').classList.add('hidden')"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
            <div class="bg-white px-6 pt-6 pb-2">
                <div class="flex items-center justify-between mb-4">
                    <h2 id="metaSheetGuideModalTitle" class="text-xl font-semibold text-gray-900">How to Connect Meta/Facebook Leads via Google Sheets</h2>
                    <button type="button" onclick="document.getElementById('metaSheetGuideModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 rounded-lg p-1">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="max-h-[70vh] overflow-y-auto pr-2 text-gray-700 text-sm space-y-6">
                    <p class="text-gray-600">Follow these steps to send leads from your Meta (Facebook) lead form into this CRM using a Google Sheet. No coding required.</p>

                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Part A – What to do in Meta/Facebook</h3>
                        <ol class="list-decimal list-inside space-y-2 pl-1">
                            <li><strong>Create a lead form in Meta (Facebook).</strong> In Meta Business Suite or Ads Manager, create a lead form for your campaign. Add the fields you need (e.g. name, phone, email). You will connect this form to a Google Sheet so that when someone submits the form, their response is saved in the sheet.</li>
                            <li><strong>Connect the lead form to Google Sheets.</strong> Use Meta’s option to send leads to a Google Sheet (e.g. in the form’s “Integrations” or “Lead destination” and choose Google Sheets), or use a tool like Zapier or Make that sends each new lead to a row in your sheet. Create a new Google Sheet (or use an existing one). Make sure each new lead from Meta is written as a new row in the sheet, with columns for name, phone, email, and any other fields you use.</li>
                            <li><strong>Note your Sheet URL and tab name.</strong> Open the Google Sheet in your browser. Copy the full URL from the address bar (it looks like <code class="bg-gray-100 px-1 rounded">https://docs.google.com/spreadsheets/d/XXXXX/edit</code>). Note the name of the tab (sheet) where leads appear (e.g. “Sheet1” or “Leads”). You will need this URL and tab name in the CRM in the next part.</li>
                        </ol>
                    </div>

                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Part B – What to do in this CRM</h3>
                        <ol class="list-decimal list-inside space-y-2 pl-1" start="4">
                            <li><strong>Open the Meta Sheet page and start setup.</strong> In this CRM, go to Integrations and then Meta Sheet. Click the “Add New Meta Sheet” button.</li>
                            <li><strong>Step 1 – Info.</strong> Read the short description and click “Get Started”. This confirms you are setting up a Meta/Facebook lead form via Google Sheets.</li>
                            <li><strong>Step 2 – Google Sheet configuration.</strong> Paste the Google Sheet URL (or just the sheet ID) that you copied from your browser. Enter the sheet (tab) name where leads are written (e.g. “Sheet1”). Click “Auto-Detect Columns” so the CRM can read your sheet columns. Save and go to the next step.</li>
                            <li><strong>Step 3 – Field mapping.</strong> Match each sheet column (e.g. “Name”, “Phone”) to the CRM field (e.g. Lead name, Phone number). The CRM will use this mapping to create leads from each new row in the sheet.</li>
                            <li><strong>Step 4 – Status columns.</strong> Choose which columns in your sheet should receive updates from the CRM (e.g. “Status”, “Assigned to”). When a lead is updated in the CRM, the CRM can write back the status into these columns.</li>
                            <li><strong>Step 5 – Google Apps Script.</strong> The CRM will show you a script to copy. In Google Sheets, go to Extensions → Apps Script, paste the script, and deploy it (e.g. “Deploy” → “New deployment”, choose “Web app”). This script allows the sheet to talk to the CRM so that new leads sync automatically. You do not need to write code; just copy, paste, and deploy as instructed.</li>
                            <li><strong>Step 6 – Test and complete.</strong> Use the “Test” button to run a test sync. If the test succeeds, activate the integration. From now on, new leads that appear in your Google Sheet (from Meta) will sync into the CRM automatically.</li>
                        </ol>
                    </div>

                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-green-800 text-sm"><strong>Summary:</strong> Leads from your Meta/Facebook form are saved in a Google Sheet. This CRM reads that sheet (using the script you deployed) and creates or updates leads. You only need to set up the Meta side once (form + sheet connection) and then complete this 6-step wizard once.</p>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3 flex justify-end">
                <button type="button" onclick="document.getElementById('metaSheetGuideModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 text-sm font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
