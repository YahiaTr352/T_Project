<div class="row">
    @foreach (['parent_id' => ['الأب', 'male', $males], 'mother_id' => ['الأم', 'female', $females]] as $field => [$label, $gender, $options])
        @php
            $fieldId = $idPrefix . $field;
            $fixed = isset($knownParent) && $knownParent->gender === $gender;
        @endphp
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="{{ $fieldId }}" class="ppl-form-label">
                    {{ $label }} <span class="text-danger">*</span>
                </label>
                @if ($fixed)
                    <input type="hidden" name="{{ $field }}" value="{{ $knownParent->id }}">
                    <input type="text" id="{{ $fieldId }}" class="form-control ppl-form-control" value="{{ $knownParent->full_name }}" readonly>
                @else
                    <select id="{{ $fieldId }}" name="{{ $field }}" class="form-control ppl-form-control js-required-parent" required>
                        <option value="">-- اختر {{ $label }} --</option>
                        @foreach ($options as $option)
                            <option value="{{ $option->id }}" @selected((string) old($field) === (string) $option->id)>
                                {{ $option->full_name }}
                            </option>
                        @endforeach
                    </select>
                @endif
                @error($field)
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>
    @endforeach
</div>

@once
    @push('scripts')
        <script>
            $(function () {
                $('.js-required-parent').each(function () {
                    const $select = $(this);
                    const $modal = $select.closest('.modal');

                    $select.select2({
                        theme: 'bootstrap4',
                        width: '100%',
                        dropdownParent: $modal.length ? $modal : $(document.body),
                    });
                });
            });
        </script>
    @endpush
@endonce
