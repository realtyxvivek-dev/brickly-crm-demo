@php
    $hideOvertimeForManagerAttendance = auth()->check()
        && (auth()->user()->isSalesManager() || auth()->user()->isSeniorManager() || auth()->user()->isAssistantSalesManager());
@endphp
<div class="att-subnav bg-white rounded-2xl shadow-sm border border-[#E5DED4] p-3">
    <div class="flex flex-wrap gap-3 text-sm">
        @if($hideOvertimeForManagerAttendance)
            <a href="{{ route('sales-manager.attendance') }}" class="att-subnav-link px-4 py-2 rounded-xl border {{ request()->routeIs('sales-manager.attendance') ? 'bg-[#205A44] text-white border-[#205A44]' : 'border-[#E5DED4] text-brand-primary' }}">
                Attendance
            </a>
        @endif
        <a href="{{ route('attendance.leaves') }}" class="att-subnav-link px-4 py-2 rounded-xl border {{ request()->routeIs('attendance.leaves*') ? 'bg-[#205A44] text-white border-[#205A44]' : 'border-[#E5DED4] text-brand-primary' }}">Leaves</a>
        <a href="{{ route('attendance.regularizations') }}" class="att-subnav-link px-4 py-2 rounded-xl border {{ request()->routeIs('attendance.regularizations*') ? 'bg-[#205A44] text-white border-[#205A44]' : 'border-[#E5DED4] text-brand-primary' }}">Regularization</a>
        @unless($hideOvertimeForManagerAttendance)
            <a href="{{ route('attendance.overtimes') }}" class="att-subnav-link px-4 py-2 rounded-xl border {{ request()->routeIs('attendance.overtimes') ? 'bg-[#205A44] text-white border-[#205A44]' : 'border-[#E5DED4] text-brand-primary' }}">Overtime</a>
        @endunless
        <a href="{{ route('attendance.payslips') }}" class="att-subnav-link px-4 py-2 rounded-xl border {{ request()->routeIs('attendance.payslips') ? 'bg-[#205A44] text-white border-[#205A44]' : 'border-[#E5DED4] text-brand-primary' }}">Payslips</a>
    </div>
</div>
