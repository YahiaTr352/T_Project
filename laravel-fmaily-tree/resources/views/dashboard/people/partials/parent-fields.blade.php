<div class="row js-parent-fields">
    @foreach (['parent_id' => ['الأب', 'male', $males], 'mother_id' => ['الأم', 'female', $females]] as $field => [$label, $gender, $options])
        @php
            $fieldId = $idPrefix . $field;
            $fixed = isset($knownParent) && $knownParent->gender === $gender;
        @endphp
        <div class="col-md-6 mb-3">
            <div class="form-group mb-0">
                <label for="{{ $fieldId }}" class="ppl-form-label">
                    {{ $label }} <span class="text-danger js-parent-required-marker" @if (!$parentsRequired) hidden @endif>*</span>
                </label>
                @if ($fixed)
                    <input type="hidden" name="{{ $field }}" value="{{ $knownParent->id }}">
                    <input type="text" id="{{ $fieldId }}" class="form-control ppl-form-control" value="{{ $knownParent->full_name }}" readonly>
                @else
                    <select id="{{ $fieldId }}" name="{{ $field }}" class="form-control ppl-form-control js-parent-select" @required($parentsRequired)>
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
                $('.js-parent-fields').each(function () {
                    const $fields = $(this);
                    const $parents = $fields.find('.js-parent-select');
                    const $modal = $fields.closest('.modal');
                    const $affiliation = $fields.closest('form').find('select[name="from_outside_the_family"]');

                    $parents.select2({
                        theme: 'bootstrap4',
                        width: '100%',
                        dropdownParent: $modal.length ? $modal : $(document.body),
                    });

                    $affiliation.on('change', function () {
                        const required = this.value === '0';
                        $parents.prop('required', required);
                        $fields.find('.js-parent-required-marker').prop('hidden', !required);
                    }).trigger('change');
                });
            });
        </script>
    @endpush
@endonce
