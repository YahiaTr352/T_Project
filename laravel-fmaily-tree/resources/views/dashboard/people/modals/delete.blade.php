@if(isset($person))
    <div class="modal fade ppl-modal" id="deletePersonModal{{ $person->id }}" tabindex="-1" role="dialog"
        aria-labelledby="deletePersonModalLabel{{ $person->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content text-right">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title text-danger d-flex align-items-center" id="deletePersonModalLabel{{ $person->id }}">
                        <i class="fas fa-exclamation-triangle ml-2"></i>
                        <span>تأكيد حذف الشخص</span>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="{{ route('people.destroy', $person->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-body text-center p-4">
                        <div class="position-relative d-inline-block mb-3">
                            <img src="{{ $person->avatar }}" alt="{{ $person->full_name }}"
                                class="rounded-circle shadow-sm" 
                                style="width: 90px; height: 90px; object-fit: cover; border: 3px solid #ef4444;">
                        </div>
                        
                        <h4 class="font-weight-bold mb-2 ppl-text-title">{{ $person->full_name }}</h4>
                        
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <span class="ppl-badge {{ $person->gender == 'male' ? 'ppl-badge-male' : 'ppl-badge-female' }}">
                                <i class="fas {{ $person->gender == 'male' ? 'fa-mars' : 'fa-venus' }}"></i>
                                {{ $person->gender == 'male' ? 'ذكر' : 'أنثى' }}
                            </span>
                            @if(!$person->from_outside_the_family)
                                <span class="ppl-badge ppl-badge-inside">
                                    <i class="fas fa-home"></i> داخل العائلة
                                </span>
                            @else
                                <span class="ppl-badge ppl-badge-outside">
                                    <i class="fas fa-user-friends"></i> خارج العائلة
                                </span>
                            @endif
                        </div>

                        @php
                            $descendantsCount = $person->children()->count();
                        @endphp

                        @if ($descendantsCount > 0)
                            <div class="alert alert-danger text-right py-3 px-3 rounded-lg" style="font-size: 0.95rem; line-height: 1.6;">
                                <h6 class="font-weight-bold text-danger mb-2 d-flex align-items-center">
                                    <i class="fas fa-exclamation-circle ml-2"></i> تنبيه هام جداً (حذف متسلسل)
                                </h6>
                                هذا الشخص لديه <strong>{{ $descendantsCount }}</strong> من الأبناء المباشرين في شجرة العائلة. سيؤدي حذفه إلى حذف كافة التابعين له تلقائياً.
                            </div>
                        @else
                            <div class="alert alert-warning text-right py-3 px-3 rounded-lg" style="font-size: 0.95rem; line-height: 1.6;">
                                <i class="fas fa-info-circle ml-1"></i> هل أنت متأكد من حذف هذا السجل نهائياً من قاعدة البيانات؟ لا يمكن التراجع عن هذه العملية بعد التأكيد.
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary px-4 py-2" data-dismiss="modal" style="font-size: 0.95rem; font-weight: 600;">
                            إلغاء وتراجع
                        </button>
                        <button type="submit" class="btn btn-danger px-4 py-2 shadow-sm" style="font-size: 0.95rem; font-weight: 700;">
                            <i class="fas fa-trash-alt ml-1"></i> نعم، حذف نهائي
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
