<div class="dashboard-notif-card p-4 mb-4">
    <form method="GET" class="row g-3 items-end">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="col-md-2">
            <label for="filter-user" class="text-xs font-bold notif-subtitle mb-1.5 block">المستخدم:</label>
            <select id="filter-user" name="user_id" class="dashboard-input py-1.5 text-xs">
                <option value="">— الجميع —</option>
                @foreach($filterOptions['users'] as $id => $name)
                    <option value="{{ $id }}" @selected(request('user_id') == $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label for="filter-model" class="text-xs font-bold notif-subtitle mb-1.5 block">نوع العنصر:</label>
            <select id="filter-model" name="{{ $tab === 'activity' ? 'subject_type' : 'auditable_type' }}" class="dashboard-input py-1.5 text-xs">
                <option value="">— جميع الأنواع —</option>
                @foreach($filterOptions['types'] as $type => $label)
                    @php
                        $selected = $tab === 'activity'
                            ? request('subject_type') === $type
                            : request('auditable_type') === $type;
                    @endphp
                    <option value="{{ $type }}" @selected($selected)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label for="filter-event" class="text-xs font-bold notif-subtitle mb-1.5 block">نوع الحدث:</label>
            <select id="filter-event" name="event" class="dashboard-input py-1.5 text-xs">
                <option value="">— جميع الأحداث —</option>
                @foreach($filterOptions['events'] as $event)
                    <option value="{{ $event }}" @selected(request('event') === $event)>
                        {{ \App\Support\LogHumanizer::eventLabel($event) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label for="filter-from" class="text-xs font-bold notif-subtitle mb-1.5 block">من تاريخ:</label>
            <input type="date" id="filter-from" name="from" value="{{ request('from') }}" class="dashboard-input py-1.5 text-xs">
        </div>

        <div class="col-md-2">
            <label for="filter-to" class="text-xs font-bold notif-subtitle mb-1.5 block">إلى تاريخ:</label>
            <input type="date" id="filter-to" name="to" value="{{ request('to') }}" class="dashboard-input py-1.5 text-xs">
        </div>

        @if($tab === 'activity')
            <div class="col-md-3">
                <label for="filter-request-id" class="text-xs font-bold notif-subtitle mb-1.5 block">معرّف الطلب (Request ID):</label>
                <input type="text" id="filter-request-id" name="request_id" value="{{ request('request_id') }}" class="dashboard-input py-1.5 text-xs" placeholder="اختياري">
            </div>
        @endif

        <div class="col-md-12 col-lg-auto d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary btn-sm rounded-xl text-xs font-bold border-0 py-2 px-4">
                <i class="fas fa-search ml-1"></i> تطبيق
            </button>
            <a href="{{ route('logs.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary btn-sm rounded-xl text-xs font-bold py-2 px-4">
                <i class="fas fa-times ml-1"></i> مسح الفلاتر
            </a>
        </div>
    </form>
</div>
