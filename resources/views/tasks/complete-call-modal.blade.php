<!-- Complete Call Modal -->
<div id="complete-call-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-4xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Complete Call - Update Lead Details</h3>
                <button onclick="closeCompleteCallModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            @if(isset($dynamicForm) && $dynamicForm)
                <!-- Dynamic Form -->
                <div id="dynamic-form-container">
                    <input type="hidden" id="task_id" name="task_id" value="">
                    <x-dynamic-form :form="$dynamicForm" />
                </div>
                <script>
                    // Override form submission for dynamic form to use existing endpoint
                    document.addEventListener('DOMContentLoaded', function() {
                        const dynamicForm = document.querySelector('#dynamic-form-container form');
                        if (dynamicForm) {
                            dynamicForm.addEventListener('submit', function(e) {
                                e.preventDefault();
                                const taskId = document.getElementById('task_id')?.value;
                                if (!taskId) return;
                                
                                const formData = new FormData(this);
                                formData.append('task_id', taskId);
                                
                                axios.post(`/tasks/${taskId}/update-lead`, formData)
                                    .then(response => {
                                        if (response.data.success) {
                                            alert('Lead updated successfully!');
                                            closeCompleteCallModal();
                                            window.location.reload();
                                        } else {
                                            alert('Failed to update lead: ' + (response.data.message || 'Unknown error'));
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error updating lead:', error);
                                        if (error.response?.data?.errors) {
                                            const errors = Object.values(error.response.data.errors).flat().join('\n');
                                            alert('Validation errors:\n' + errors);
                                        } else {
                                            alert('Error: ' + (error.response?.data?.message || 'Failed to update lead'));
                                        }
                                    });
                            });
                        }
                    });
                </script>
            @else
            <!-- Original Form -->
            <form id="complete-call-form">
                @csrf
                <input type="hidden" id="task_id" name="task_id">
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" id="lead_name" name="name" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                        <input type="text" id="lead_phone" name="phone" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="lead_email" name="email" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea id="lead_address" name="address" rows="2" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                        <input type="text" id="lead_city" name="city" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">State</label>
                        <input type="text" id="lead_state" name="state" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pincode</label>
                        <input type="text" id="lead_pincode" name="pincode" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Location</label>
                    <input type="text" id="lead_preferred_location" name="preferred_location" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Preferred Size</label>
                        <input type="text" id="lead_preferred_size" name="preferred_size" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Type</label>
                        <select id="lead_property_type" name="property_type" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">-- Select Type --</option>
                            <option value="apartment">Apartment</option>
                            <option value="villa">Villa</option>
                            <option value="plot">Plot</option>
                            <option value="commercial">Commercial</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Budget Min</label>
                        <input type="number" id="lead_budget_min" name="budget_min" step="0.01" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Budget Max</label>
                        <input type="number" id="lead_budget_max" name="budget_max" step="0.01" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Investment</label>
                        <input type="number" id="lead_investment" name="investment" step="0.01" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Source</label>
                    <select id="lead_source" name="source" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="other">Other</option>
                        <option value="website">Website</option>
                        <option value="referral">Referral</option>
                        <option value="walk_in">Walk In</option>
                        <option value="call">Call</option>
                        <option value="social_media">Social Media</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Use/End Use</label>
                    <textarea id="lead_use_end_use" name="use_end_use" rows="2" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Requirements</label>
                    <textarea id="lead_requirements" name="requirements" rows="2" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea id="lead_notes" name="notes" rows="2" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeCompleteCallModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-gradient-to-r from-[#063A1C] to-[#205A44] text-white rounded-lg hover:from-[#205A44] hover:to-[#15803d]">
                        Save & Complete
                    </button>
                </div>
            </form>
            @endif
        </div>
    </div>
</div>

