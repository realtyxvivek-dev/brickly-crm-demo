<div id="filters-panel" class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Filters</h3>
        <button onclick="toggleFilters()" class="text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    </div>

    <div id="filters-content" class="space-y-4 hidden sm:block">
        <!-- Search -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
            <input type="text" id="search-input" name="search" value="{{ request('search') }}" 
                   placeholder="Search by title, lead name, phone..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select id="status-filter" name="status[]" multiple 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="pending" {{ in_array('pending', (array)request('status', [])) ? 'selected' : '' }}>Pending</option>
                    <option value="in_progress" {{ in_array('in_progress', (array)request('status', [])) ? 'selected' : '' }}>In Progress</option>
                    <option value="completed" {{ in_array('completed', (array)request('status', [])) ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ in_array('cancelled', (array)request('status', [])) ? 'selected' : '' }}>Cancelled</option>
                    <option value="overdue" {{ in_array('overdue', (array)request('status', [])) ? 'selected' : '' }}>Overdue</option>
                </select>
            </div>

            <!-- Type Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                <select id="type-filter" name="type[]" multiple 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="phone_call" {{ in_array('phone_call', (array)request('type', [])) ? 'selected' : '' }}>Phone Call</option>
                    <option value="email" {{ in_array('email', (array)request('type', [])) ? 'selected' : '' }}>Email</option>
                    <option value="meeting" {{ in_array('meeting', (array)request('type', [])) ? 'selected' : '' }}>Meeting</option>
                    <option value="site_visit" {{ in_array('site_visit', (array)request('type', [])) ? 'selected' : '' }}>Site Visit</option>
                    <option value="other" {{ in_array('other', (array)request('type', [])) ? 'selected' : '' }}>Other</option>
                </select>
            </div>

            <!-- Priority Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                <select id="priority-filter" name="priority[]" multiple 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="low" {{ in_array('low', (array)request('priority', [])) ? 'selected' : '' }}>Low</option>
                    <option value="medium" {{ in_array('medium', (array)request('priority', [])) ? 'selected' : '' }}>Medium</option>
                    <option value="high" {{ in_array('high', (array)request('priority', [])) ? 'selected' : '' }}>High</option>
                    <option value="urgent" {{ in_array('urgent', (array)request('priority', [])) ? 'selected' : '' }}>Urgent</option>
                </select>
            </div>
        </div>

        <!-- Date Range -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date From</label>
                <input type="date" id="date-from" name="date_from" value="{{ request('date_from') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Date To</label>
                <input type="date" id="date-to" name="date_to" value="{{ request('date_to') }}" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
            </div>
        </div>

        <!-- Quick Filters -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Quick Filters</label>
            <div class="flex flex-wrap gap-2">
                <button type="button" onclick="applyQuickFilter('today')" 
                        class="px-3 py-1 text-sm rounded-full border border-gray-300 hover:bg-gray-50 {{ request('filter') == 'today' ? 'bg-indigo-50 border-indigo-500 text-indigo-700' : '' }}">
                    Today
                </button>
                <button type="button" onclick="applyQuickFilter('overdue')" 
                        class="px-3 py-1 text-sm rounded-full border border-gray-300 hover:bg-gray-50 {{ request('filter') == 'overdue' ? 'bg-red-50 border-red-500 text-red-700' : '' }}">
                    Overdue
                </button>
                <button type="button" onclick="applyQuickFilter('urgent')" 
                        class="px-3 py-1 text-sm rounded-full border border-gray-300 hover:bg-gray-50 {{ request('filter') == 'urgent' ? 'bg-orange-50 border-orange-500 text-orange-700' : '' }}">
                    Urgent
                </button>
                <button type="button" onclick="applyQuickFilter('my_tasks')" 
                        class="px-3 py-1 text-sm rounded-full border border-gray-300 hover:bg-gray-50 {{ request('filter') == 'my_tasks' ? 'bg-blue-50 border-blue-500 text-blue-700' : '' }}">
                    My Tasks
                </button>
            </div>
        </div>

        <!-- Active Filters -->
        @if(request()->hasAny(['status', 'type', 'priority', 'search', 'date_from', 'date_to', 'filter']))
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-sm font-medium text-gray-700">Active Filters:</span>
            @if(request('status'))
                @foreach((array)request('status') as $status)
                    <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full">
                        Status: {{ ucfirst($status) }}
                        <button onclick="removeFilter('status', '{{ $status }}')" class="ml-1">×</button>
                    </span>
                @endforeach
            @endif
            @if(request('search'))
                <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded-full">
                    Search: {{ request('search') }}
                    <button onclick="removeSearchFilter()" class="ml-1">×</button>
                </span>
            @endif
            <button onclick="clearAllFilters()" class="text-sm text-red-600 hover:text-red-800">Clear All</button>
        </div>
        @endif

        <!-- Apply Filters Button -->
        <div class="flex justify-end gap-2">
            <button onclick="clearAllFilters()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Clear
            </button>
            <button onclick="applyFilters()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                Apply Filters
            </button>
        </div>
    </div>
</div>

<script>
function toggleFilters() {
    const content = document.getElementById('filters-content');
    content.classList.toggle('hidden');
    
    const button = event.currentTarget.querySelector('svg');
    if (button) {
        if (content.classList.contains('hidden')) {
            button.style.transform = 'rotate(0deg)';
        } else {
            button.style.transform = 'rotate(180deg)';
        }
    }
}

function applyQuickFilter(filter) {
    const url = new URL(window.location.href);
    url.searchParams.set('filter', filter);
    window.location.href = url.toString();
}

function applyFilters() {
    const url = new URL(window.location.href);
    
    // Clear existing filters
    url.searchParams.delete('status');
    url.searchParams.delete('type');
    url.searchParams.delete('priority');
    url.searchParams.delete('date_from');
    url.searchParams.delete('date_to');
    url.searchParams.delete('search');
    url.searchParams.delete('filter');
    
    // Add new filters
    const statusFilter = document.getElementById('status-filter');
    if (statusFilter.selectedOptions.length > 0) {
        Array.from(statusFilter.selectedOptions).forEach(option => {
            url.searchParams.append('status[]', option.value);
        });
    }
    
    const typeFilter = document.getElementById('type-filter');
    if (typeFilter.selectedOptions.length > 0) {
        Array.from(typeFilter.selectedOptions).forEach(option => {
            url.searchParams.append('type[]', option.value);
        });
    }
    
    const priorityFilter = document.getElementById('priority-filter');
    if (priorityFilter.selectedOptions.length > 0) {
        Array.from(priorityFilter.selectedOptions).forEach(option => {
            url.searchParams.append('priority[]', option.value);
        });
    }
    
    const dateFrom = document.getElementById('date-from').value;
    if (dateFrom) {
        url.searchParams.set('date_from', dateFrom);
    }
    
    const dateTo = document.getElementById('date-to').value;
    if (dateTo) {
        url.searchParams.set('date_to', dateTo);
    }
    
    const search = document.getElementById('search-input').value;
    if (search) {
        url.searchParams.set('search', search);
    }
    
    window.location.href = url.toString();
}

function removeFilter(filterType, value) {
    const url = new URL(window.location.href);
    const params = url.searchParams.getAll(filterType + '[]');
    const newParams = params.filter(p => p !== value);
    url.searchParams.delete(filterType + '[]');
    newParams.forEach(p => url.searchParams.append(filterType + '[]', p));
    window.location.href = url.toString();
}

function removeSearchFilter() {
    const url = new URL(window.location.href);
    url.searchParams.delete('search');
    window.location.href = url.toString();
}

function clearAllFilters() {
    const url = new URL(window.location.href);
    url.searchParams.delete('status');
    url.searchParams.delete('type');
    url.searchParams.delete('priority');
    url.searchParams.delete('date_from');
    url.searchParams.delete('date_to');
    url.searchParams.delete('search');
    url.searchParams.delete('filter');
    window.location.href = url.toString();
}

// Enter key on search
document.getElementById('search-input')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});
</script>
