<div class="mb-4">
    <label class="block text-sm font-medium text-gray-700 mb-2">Recurrence Pattern</label>
    <select id="recurrence-frequency" name="recurrence_frequency" 
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
            onchange="toggleRecurrenceOptions()">
        <option value="">None (One-time task)</option>
        <option value="daily">Daily</option>
        <option value="weekly">Weekly</option>
        <option value="monthly">Monthly</option>
        <option value="yearly">Yearly</option>
    </select>

    <div id="recurrence-options" class="hidden mt-3 space-y-3">
        <!-- Weekly options -->
        <div id="weekly-options" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">Days of week</label>
            <div class="flex flex-wrap gap-2">
                <label class="flex items-center">
                    <input type="checkbox" name="recurrence_days[]" value="0" class="rounded border-gray-300">
                    <span class="ml-2 text-sm">Mon</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="recurrence_days[]" value="1" class="rounded border-gray-300">
                    <span class="ml-2 text-sm">Tue</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="recurrence_days[]" value="2" class="rounded border-gray-300">
                    <span class="ml-2 text-sm">Wed</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="recurrence_days[]" value="3" class="rounded border-gray-300">
                    <span class="ml-2 text-sm">Thu</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="recurrence_days[]" value="4" class="rounded border-gray-300">
                    <span class="ml-2 text-sm">Fri</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="recurrence_days[]" value="5" class="rounded border-gray-300">
                    <span class="ml-2 text-sm">Sat</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="recurrence_days[]" value="6" class="rounded border-gray-300">
                    <span class="ml-2 text-sm">Sun</span>
                </label>
            </div>
        </div>

        <!-- Monthly options -->
        <div id="monthly-options" class="hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">Day of month</label>
            <input type="number" name="recurrence_day_of_month" min="1" max="31" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                   placeholder="Day (1-31)">
        </div>

        <!-- End date -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Recurrence End Date (Optional)</label>
            <input type="date" name="recurrence_end_date" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            <p class="mt-1 text-xs text-gray-500">Leave empty for indefinite recurrence</p>
        </div>
    </div>
</div>

<script>
function toggleRecurrenceOptions() {
    const frequency = document.getElementById('recurrence-frequency').value;
    const optionsDiv = document.getElementById('recurrence-options');
    const weeklyDiv = document.getElementById('weekly-options');
    const monthlyDiv = document.getElementById('monthly-options');

    if (frequency) {
        optionsDiv.classList.remove('hidden');
        if (frequency === 'weekly') {
            weeklyDiv.classList.remove('hidden');
            monthlyDiv.classList.add('hidden');
        } else if (frequency === 'monthly') {
            weeklyDiv.classList.add('hidden');
            monthlyDiv.classList.remove('hidden');
        } else {
            weeklyDiv.classList.add('hidden');
            monthlyDiv.classList.add('hidden');
        }
    } else {
        optionsDiv.classList.add('hidden');
    }
}
</script>
