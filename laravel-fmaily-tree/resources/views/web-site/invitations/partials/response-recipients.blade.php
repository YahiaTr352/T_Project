@php
    $recipients = $notification->recipients;
    $appRecipients = $recipients->whereNotNull('person_id');
@endphp

<div class="mt-4 border-t border-gray-100 pt-4">
    @if($appRecipients->isNotEmpty())
        <dl class="mb-3 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 text-center">
            <div>
                <dt class="text-xs text-slate-600">موافق</dt>
                <dd class="font-bold text-emerald-800">{{ $appRecipients->where('invitation_response', 'accepted')->count() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-600">معتذر</dt>
                <dd class="font-bold text-rose-800">{{ $appRecipients->where('invitation_response', 'declined')->count() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-600">بانتظار الرد</dt>
                <dd class="font-bold text-slate-800">{{ $appRecipients->whereNull('invitation_response')->count() }}</dd>
            </div>
        </dl>
    @endif
    <details @if($recipients->count() <= 10) open @endif>
        <summary class="cursor-pointer text-sm font-bold text-slate-800">ردود المستلمين ({{ $recipients->count() }})</summary>
        <ul class="mt-2 grid gap-2 sm:grid-cols-2">
            @foreach($recipients as $recipient)
                @php
                    $responseLabel = $recipient->person_id === null ? 'خارج التطبيق' : match ($recipient->invitation_response) {
                        'accepted' => 'موافق على الحضور',
                        'declined' => 'معتذر عن الحضور',
                        default => 'بانتظار الرد',
                    };
                @endphp
                <li class="flex flex-wrap justify-between gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm">
                    <span class="font-medium text-slate-800">{{ $recipient->person?->first_name ?? $recipient->recipient_name ?? 'ضيف' }}</span>
                    <span class="text-xs text-slate-600">{{ $responseLabel }}</span>
                </li>
            @endforeach
        </ul>
    </details>
</div>
