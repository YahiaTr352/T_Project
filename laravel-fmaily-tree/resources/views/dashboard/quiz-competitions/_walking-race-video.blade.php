<div class="modal fade" id="walkingRaceVideoModal" tabindex="-1" aria-labelledby="walkingRaceVideoTitle" aria-hidden="true"
     data-avatar-male="{{ asset('assets/img/avatar-male.png') }}"
     data-avatar-female="{{ asset('assets/img/avatar-female-hejap-1.png') }}"
     data-avatar-group="{{ asset('assets/img/default-group-cover.png') }}">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="walkingRaceVideoTitle">حفظ فيديو سباق الترتيب</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="row align-items-start">
                    <form id="walkingRaceVideoForm" class="col-md-6">
                        <p class="text-muted">فيديو عمودي بصيغة MP4 يعرض أول ١٠ مراكز حتى آخر يوم متاح.</p>
                        <div class="form-group d-none" id="walkingRaceVideoSectionField">
                            <label for="walkingRaceVideoSection">الترتيب</label>
                            <select class="custom-select" id="walkingRaceVideoSection"></select>
                        </div>
                        <div class="form-group">
                            <label for="walkingRaceVideoDuration">مدة الفيديو بالثواني</label>
                            <input class="form-control" id="walkingRaceVideoDuration" type="number" value="30" min="5" max="60" step="1" required
                                   aria-describedby="walkingRaceVideoDurationHint">
                            <small class="form-text text-muted" id="walkingRaceVideoDurationHint">من ٥ إلى ٦٠ ثانية.</small>
                        </div>
                        <p class="small mb-2" id="walkingRaceVideoStatus" role="status"></p>
                        <progress class="d-none w-100" id="walkingRaceVideoProgress" max="100" aria-label="تقدّم تجهيز الفيديو"></progress>
                    </form>
                    <div class="col-md-6 text-center">
                        <canvas class="d-none mw-100 rounded" id="walkingRaceVideoCanvas" width="720" height="1280" style="width: 280px; height: auto;" aria-label="معاينة ترتيب السباق"></canvas>
                        <video class="d-none mw-100 rounded" id="walkingRaceVideoPreview" style="width: 280px; height: auto;" controls playsinline aria-label="معاينة فيديو السباق"></video>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">إغلاق</button>
                <button type="submit" form="walkingRaceVideoForm" id="walkingRaceVideoGenerate" class="btn btn-primary" disabled>تجهيز الفيديو</button>
                <a id="walkingRaceVideoDownload" class="btn btn-success d-none" download>تنزيل الفيديو</a>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    @font-face {
        font-family: 'Tajawal Race';
        src: url('{{ asset('fonts/Tajawal-Regular.ttf') }}') format('truetype');
        font-weight: 400;
    }
    @font-face {
        font-family: 'Tajawal Race';
        src: url('{{ asset('fonts/Tajawal-Bold.ttf') }}') format('truetype');
        font-weight: 700;
    }
</style>
@endpush

@push('scripts')
    @vite('resources/js/dashboard/walking-race-video.js')
@endpush
