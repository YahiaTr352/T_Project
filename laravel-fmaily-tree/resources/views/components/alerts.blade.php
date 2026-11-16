<style>
    .alert-premium {
        display: flex;
        align-items: center;
        padding: 1rem 1.25rem;
        border-radius: 12px;
        margin-bottom: 1.25rem;
        font-weight: 500;
        border: 1px solid;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        animation: alertFadeIn 0.3s ease-out;
        position: relative;
        direction: rtl;
        text-align: right;
    }
    .alert-premium.success {
        background-color: #f0fdf4;
        border-color: #bbf7d0;
        color: #166534;
    }
    .alert-premium.error, .alert-premium.danger {
        background-color: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
    }
    .alert-premium.warning {
        background-color: #fffbeb;
        border-color: #fef3c7;
        color: #92400e;
    }
    .alert-premium.info {
        background-color: #eff6ff;
        border-color: #bfdbfe;
        color: #1e3a8a;
    }
    .alert-premium .close-btn {
        margin-right: auto;
        background: none;
        border: none;
        color: inherit;
        font-size: 1.2rem;
        cursor: pointer;
        opacity: 0.6;
        transition: opacity 0.2s;
        padding: 0 0.5rem;
        line-height: 1;
    }
    .alert-premium .close-btn:hover {
        opacity: 1;
    }
    .alert-premium i {
        margin-left: 0.75rem;
        font-size: 1.15rem;
    }
    @keyframes alertFadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

@if(session('success'))
    <div class="alert-premium success" role="alert" id="alert-success">
        <i class="fas fa-check-circle"></i>
        <span>{{ session('success') }}</span>
        <button type="button" class="close-btn" onclick="document.getElementById('alert-success').remove()" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if(session('error'))
    <div class="alert-premium error" role="alert" id="alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span>{{ session('error') }}</span>
        <button type="button" class="close-btn" onclick="document.getElementById('alert-error').remove()" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if(session('warning'))
    <div class="alert-premium warning" role="alert" id="alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <span>{{ session('warning') }}</span>
        <button type="button" class="close-btn" onclick="document.getElementById('alert-warning').remove()" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if(session('info'))
    <div class="alert-premium info" role="alert" id="alert-info">
        <i class="fas fa-info-circle"></i>
        <span>{{ session('info') }}</span>
        <button type="button" class="close-btn" onclick="document.getElementById('alert-info').remove()" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if($errors->any())
    <div class="alert-premium error" role="alert" id="alert-errors">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>حدث خطأ:</strong>
            <ul class="mb-0 mt-1" style="padding-right: 1.25rem; list-style-type: disc;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="close-btn" onclick="document.getElementById('alert-errors').remove()" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif
