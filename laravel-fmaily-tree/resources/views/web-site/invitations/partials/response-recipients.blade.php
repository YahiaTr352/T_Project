@php
    $recipients = $notification->recipients;
    $appRecipients = $recipients->whereNotNull('person_id');
@endphp

<div class="mt-4 border-t pt-4" style="border-color: var(--inv-border);">
    @if($appRecipients->isNotEmpty())
        <dl class="mb-3 grid grid-cols-3 gap-2.5 rounded-2xl inv-inset p-3 text-center">
            <div class="rounded-xl inv-card py-2 px-1">
                <dt class="text-xs inv-muted font-bold">موافق</dt>
                <dd class="font-black text-base mt-0.5" style="color: var(--inv-success-text)">{{ $appRecipients->where('invitation_response', 'accepted')->count() }}</dd>
            </div>
            <div class="rounded-xl inv-card py-2 px-1">
                <dt class="text-xs inv-muted font-bold">معتذر</dt>
                <dd class="font-black text-base mt-0.5" style="color: var(--inv-danger-text)">{{ $appRecipients->where('invitation_response', 'declined')->count() }}</dd>
            </div>
            <div class="rounded-xl inv-card py-2 px-1">
                <dt class="text-xs inv-muted font-bold">بانتظار الرد</dt>
                <dd class="font-black text-base mt-0.5" style="color: var(--inv-text)">{{ $appRecipients->whereNull('invitation_response')->count() }}</dd>
            </div>
        </dl>
    @endif
    <details @if($recipients->count() <= 10) open @endif>
        <summary class="cursor-pointer text-sm font-extrabold" style="color: var(--inv-text)">ردود المستلمين ({{ $recipients->count() }})</summary>
        <ul class="mt-2.5 grid gap-2 sm:grid-cols-2">
            @foreach($recipients as $recipient)
                @php
                    $responseLabel = $recipient->person_id === null ? 'خارج التطبيق' : match ($recipient->invitation_response) {
                        'accepted' => 'موافق على الحضور',
                        'declined' => 'معتذر عن الحضور',
                        default => 'بانتظار الرد',
                    };
                @endphp
                <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl inv-inset px-3 py-2 text-sm">
                    <span class="font-bold" style="color: var(--inv-text)">{{ $recipient->person?->first_name ?? $recipient->recipient_name ?? 'ضيف' }}</span>
                    <span class="text-xs inv-muted font-medium">{{ $responseLabel }}</span>
                </li>
            @endforeach
        </ul>
    </details>
</div>
