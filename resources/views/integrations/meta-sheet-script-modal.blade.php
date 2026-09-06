<!-- Script Modal -->
<div id="script-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Header -->
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    <i class="fab fa-google-drive mr-2"></i>
                    Google Apps Script
                </h3>
                <button onclick="closeScriptModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Instructions -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-blue-900 mb-2">How to use this script:</h4>
                <ol class="list-decimal list-inside text-sm text-blue-800 space-y-1">
                    <li>Copy the entire script below</li>
                    <li>Open your Google Sheet</li>
                    <li>Go to <strong>Extensions → Apps Script</strong></li>
                    <li>Delete any existing code and paste this script</li>
                    <li>Click <strong>Save</strong> (Ctrl+S or Cmd+S)</li>
                    <li>Run the <code>setupTrigger()</code> function once (Run → setupTrigger)</li>
                    <li>Authorize the script when prompted</li>
                </ol>
            </div>
            
            <!-- Script Content -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Script Code:</label>
                <textarea 
                    id="script-content" 
                    readonly 
                    class="w-full h-96 px-4 py-3 border border-gray-300 rounded-lg font-mono text-sm bg-gray-50 resize-none"
                    style="font-family: 'Courier New', monospace;"
                ></textarea>
            </div>
            
            <!-- Actions -->
            <div class="flex items-center justify-between">
                <button 
                    onclick="closeScriptModal()" 
                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300"
                >
                    <i class="fas fa-times mr-2"></i> Close
                </button>
                <button 
                    id="copy-script-btn"
                    onclick="copyScriptToClipboard()" 
                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
                >
                    <i class="fas fa-copy mr-2"></i> Copy to Clipboard
                </button>
            </div>
        </div>
    </div>
</div>
