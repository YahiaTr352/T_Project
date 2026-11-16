{{-- مودال تعديل موقع لشخص --}}
<div class="modal fade ppl-modal" id="editPersonLocationModal{{ $personLocation->id }}" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content text-right">
            <div class="modal-header border-bottom">
                <h5 class="modal-title font-weight-bold d-flex align-items-center">
                    <i class="fas fa-edit text-primary ml-2"></i>
                    <span>تعديل بيانات الموقع</span>
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="{{ route('people.locations.update', [$person->id, $personLocation->id]) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="form-group mb-3">
                        <label class="ppl-form-label">
                            <i class="fas fa-city text-primary"></i>
                            <span>الموقع أو المدينة</span>
                            <span class="text-danger">*</span>
                        </label>
                        <select name="location_id" class="form-control ppl-form-control location-select" required>
                            @foreach(\App\Models\Location::orderBy('name')->get() as $location)
                                <option value="{{ $location->id }}" @selected($personLocation->location_id == $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="ppl-form-label">
                            <i class="fas fa-tag text-info"></i>
                            <span>التسمية أو الوصف</span>
                        </label>
                        <input type="text" name="label" class="form-control ppl-form-control" value="{{ $personLocation->label }}" placeholder="مثل: مكان الإقامة، مكان العمل">
                    </div>
                    <div class="form-group mb-3">
                        <label class="ppl-form-label">
                            <i class="fas fa-link text-secondary"></i>
                            <span>رابط الموقع على الخريطة (GPS)</span>
                        </label>
                        <input type="url" name="url" class="form-control ppl-form-control" value="{{ $personLocation->url }}" placeholder="https://maps.google.com/?q=...">
                    </div>
                    <div class="custom-control custom-checkbox mt-2">
                        <input type="checkbox" name="is_primary" value="1" class="custom-control-input" id="is_primary_edit{{ $personLocation->id }}" @checked($personLocation->is_primary)>
                        <label class="custom-control-label font-weight-bold" for="is_primary_edit{{ $personLocation->id }}" style="cursor: pointer;">
                            تعيين كمقر إقامة أساسي للشخص
                        </label>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between p-3 border-top">
                    <button type="button" class="btn btn-secondary px-4 py-2" data-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm">
                        <i class="fas fa-save ml-1"></i> حفظ التعديل
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
