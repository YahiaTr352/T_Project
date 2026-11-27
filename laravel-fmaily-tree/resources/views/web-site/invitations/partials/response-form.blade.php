@php
    $response = $notification->invitation_response;
    $responseLabel = match ($response) {
        'accepted' => 'قبلت الدعوة',
        'declined' => 'رفضت الدعوة',
        default => 'بانتظار ردك',
    };
@endphp

<section class="mt-4 rounded-2xl inv-inset p-4" aria-label="الرد على الدعوة">
    <h4 class="text-sm font-extrabold" style="color: var(--inv-text)">الرد على الدعوة</h4>
    <p class="mt-1 text-sm font-bold {{ $response === 'accepted' ? 'text-emerald-600' : ($response === 'declined' ? 'text-rose-500' : 'inv-muted') }}">
        {{ $responseLabel }}
    </p>
    @if($response === null)
        <form method="POST" action="{{ route('invitations.logs.respond', ['notification' => $notification, 'page' => $notifications->currentPage(), 'direction' => $inbox ? 'notifications' : 'incoming']) }}"
            data-invitation-response-form class="mt-3 flex flex-wrap gap-2">
            @csrf
            @method('PUT')
            <input type="hidden" name="response">
            <button type="button" data-invitation-response="accepted"
                class="inv-btn-primary inline-flex min-h-[44px] items-center gap-2 px-4 py-2 text-sm cursor-pointer">
                <i class="fas fa-check" aria-hidden="true"></i> قبول الدعوة
            </button>
            <button type="button" data-invitation-response="declined"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-2xl inv-badge-danger px-4 py-2 text-sm font-bold cursor-pointer hover:opacity-85 transition">
                <i class="fas fa-times" aria-hidden="true"></i> رفض الدعوة
            </button>
        </form>
    @endif
</section>
