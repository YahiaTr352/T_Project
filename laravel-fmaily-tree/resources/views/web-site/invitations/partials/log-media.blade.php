@if ($notification->media_path)
    @php($mediaUrl = asset('storage/'.$notification->media_path))
    @if ($notification->media_type === 'image')
        <img src="{{ $mediaUrl }}" alt="صورة الدعوة" loading="lazy" data-zoomable tabindex="0" class="mt-4 max-h-96 max-w-full rounded-xl object-contain">
    @elseif ($notification->media_type === 'video')
        <video src="{{ $mediaUrl }}" controls preload="none" class="mt-4 max-h-96 max-w-full rounded-xl"></video>
    @elseif ($notification->media_type === 'voice')
        <audio src="{{ $mediaUrl }}" controls preload="none" class="mt-4 max-w-full"></audio>
    @endif
@endif
