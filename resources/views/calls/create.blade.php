@extends('layouts.app')

@section('title', 'Add Call Log - ' . brand_name())
@section('page-title', 'Add Call Log')

@section('content')
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-xl shadow-sm border border-[#E5DED4] p-6">
            @if($errors->any())
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('calls.store') }}" id="callForm">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="lead_id" class="block text-sm font-medium text-brand-primary mb-2">
                            Lead <span class="text-red-500">*</span>
                        </label>
                        <select name="lead_id" id="lead_id" required
                                class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                            <option value="">-- Select Lead --</option>
                            @foreach($leads as $lead)
                                <option value="{{ $lead->id }}" {{ old('lead_id') == $lead->id ? 'selected' : '' }}
                                        data-phone="{{ $lead->phone }}">
                                    {{ $lead->name }} ({{ $lead->phone }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="phone_number" class="block text-sm font-medium text-brand-primary mb-2">
                            Phone Number <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="phone_number" id="phone_number" required
                               value="{{ old('phone_number') }}"
                               class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                    </div>

                    <div>
                        <label for="call_type" class="block text-sm font-medium text-brand-primary mb-2">
                            Call Type <span class="text-red-500">*</span>
                        </label>
                        <select name="call_type" id="call_type" required
                                class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                            <option value="outgoing" {{ old('call_type', 'outgoing') == 'outgoing' ? 'selected' : '' }}>Outgoing</option>
                            <option value="incoming" {{ old('call_type') == 'incoming' ? 'selected' : '' }}>Incoming</option>
                        </select>
                    </div>

                    <div>
                        <label for="start_time" class="block text-sm font-medium text-brand-primary mb-2">
                            Start Time <span class="text-red-500">*</span>
                        </label>
                        <input type="datetime-local" name="start_time" id="start_time" required
                               value="{{ old('start_time', now()->format('Y-m-d\TH:i')) }}"
                               class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                    </div>

                    <div>
                        <label for="end_time" class="block text-sm font-medium text-brand-primary mb-2">
                            End Time
                        </label>
                        <input type="datetime-local" name="end_time" id="end_time"
                               value="{{ old('end_time') }}"
                               class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                    </div>

                    <div>
                        <label for="duration" class="block text-sm font-medium text-brand-primary mb-2">
                            Duration (seconds)
                        </label>
                        <input type="number" name="duration" id="duration" min="0"
                               value="{{ old('duration') }}"
                               placeholder="Auto-calculated if end time provided"
                               class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-brand-primary mb-2">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select name="status" id="status" required
                                class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                            <option value="completed" {{ old('status', 'completed') == 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="missed" {{ old('status') == 'missed' ? 'selected' : '' }}>Missed</option>
                            <option value="rejected" {{ old('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            <option value="busy" {{ old('status') == 'busy' ? 'selected' : '' }}>Busy</option>
                        </select>
                    </div>

                    <div>
                        <label for="call_outcome" class="block text-sm font-medium text-brand-primary mb-2">
                            Call Outcome
                        </label>
                        <select name="call_outcome" id="call_outcome"
                                class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                            <option value="">-- Select Outcome --</option>
                            <option value="interested" {{ old('call_outcome') == 'interested' ? 'selected' : '' }}>Interested</option>
                            <option value="not_interested" {{ old('call_outcome') == 'not_interested' ? 'selected' : '' }}>Not Interested</option>
                            <option value="callback" {{ old('call_outcome') == 'callback' ? 'selected' : '' }}>Callback Requested</option>
                            <option value="no_answer" {{ old('call_outcome') == 'no_answer' ? 'selected' : '' }}>No Answer</option>
                            <option value="busy" {{ old('call_outcome') == 'busy' ? 'selected' : '' }}>Busy</option>
                            <option value="other" {{ old('call_outcome') == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>

                    <div>
                        <label for="next_followup_date" class="block text-sm font-medium text-brand-primary mb-2">
                            Next Followup Date
                        </label>
                        <input type="datetime-local" name="next_followup_date" id="next_followup_date"
                               value="{{ old('next_followup_date') }}"
                               class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">
                    </div>

                    <div class="md:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-brand-primary mb-2">
                            Notes
                        </label>
                        <textarea name="notes" id="notes" rows="4"
                                  placeholder="Enter call notes..."
                                  class="w-full px-4 py-2 border border-[#E5DED4] rounded-lg focus:ring-2 focus:ring-brand focus:border-brand">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <div class="mt-6 flex gap-4">
                    <button type="submit" class="px-6 py-2 btn-brand-gradient text-white rounded-lg transition-colors duration-200 font-medium">
                        <i class="fas fa-save mr-2"></i> Save Call Log
                    </button>
                    <a href="{{ route('calls.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors duration-200 font-medium">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-fill phone number when lead is selected
        document.getElementById('lead_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                document.getElementById('phone_number').value = selectedOption.dataset.phone || '';
            }
        });

        // Auto-calculate duration when end time is provided
        document.getElementById('end_time').addEventListener('change', function() {
            const startTime = document.getElementById('start_time').value;
            const endTime = this.value;
            if (startTime && endTime) {
                const start = new Date(startTime);
                const end = new Date(endTime);
                const duration = Math.max(0, Math.floor((end - start) / 1000));
                document.getElementById('duration').value = duration;
            }
        });
    </script>
@endsection
