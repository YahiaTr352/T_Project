<dialog id="creativeSubmissionReview" class="creative-editor-review" aria-labelledby="creativeSubmissionReviewTitle">
    <h3 id="creativeSubmissionReviewTitle">{{ $reviewTitle ?? 'راجع التصميم قبل الإرسال' }}</h3>
    <img id="creativeSubmissionPreview" alt="معاينة التصميم مع المتغيّرات">
    <div class="creative-editor-review-actions">
        <button id="creativeSubmissionReturn" type="button" class="creative-editor-button">رجوع للتعديل</button>
        <button id="creativeSubmissionConfirm" type="button" class="creative-editor-button creative-editor-primary">{{ $confirmLabel ?? 'تأكيد الإرسال للمراجعة' }}</button>
    </div>
</dialog>
