@php
    $response = $notification->invitation_response;
    $responseLabel = match ($response) {
        'accepted' => 'قبلت الدعوة',
        'declined' => 'رفضت الدعوة',
        default => 'بانتظار ردك',
    };
@endphp

<section class="mt-4 rounded-xl border border-slate-200 bg-white p-4" aria-label="الرد على الدعوة">
    <h4 class="text-sm font-bold text-slate-900">الرد على الدعوة</h4>
    <p class="mt-1 text-sm {{ $response === 'accepted' ? 'text-emerald-800' : ($response === 'declined' ? 'text-rose-800' : 'text-slate-600') }}">
        {{ $responseLabel }}
    </p>
    @if($response === null)
    <form method="POST" action="{{ route('invitations.logs.respond', ['notification' => $notification, 'page' => $notifications->currentPage(), 'direction' => $inbox ? 'notifications' : 'incoming']) }}"
        data-invitation-response-form class="mt-3 flex flex-wrap gap-2">
        @csrf
        @method('PUT')
        <input type="hidden" name="response">
        <button type="button" data-invitation-response="accepted"
            class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-emerald-700 bg-emerald-700 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
            <i class="fas fa-check" aria-hidden="true"></i> قبول الدعوة
        </button>
        <button type="button" data-invitation-response="declined"
            class="inline-flex min-h-[44px] items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-rose-800 hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-700">
            <i class="fas fa-times" aria-hidden="true"></i> رفض الدعوة
        </button>
    </form>
    @endif
</section>
