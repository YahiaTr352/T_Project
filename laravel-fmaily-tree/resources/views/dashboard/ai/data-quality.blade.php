@extends('layouts.app')

@section('title', 'جودة بيانات العائلة')

@section('content')
<div class="container-fluid py-4" style="font-family: 'Tajawal', sans-serif;">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4 gap-3">
        <div>
            <h1 class="h3 font-bold m-0">ماسح جودة البيانات</h1>
            <p class="text-xs text-muted m-0 mt-1">
                تناقضات التواريخ، بيانات ناقصة، ومرشحو التكرار.
                @if($report['scanned_at'] ?? null)
                    — آخر فحص: {{ \Illuminate\Support\Carbon::parse($report['scanned_at'])->format('Y-m-d H:i') }}
                @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('dashboard.ai.index') }}" class="btn btn-outline-secondary btn-sm">إعدادات AI</a>
            <form method="post" action="{{ route('dashboard.ai.data-quality.scan') }}">
                @csrf
                <input type="hidden" name="sync" value="1">
                <button class="btn btn-primary btn-sm font-bold">تشغيل الفحص الآن</button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm border-0 rounded-xl h-100">
                <div class="card-body">
                    <h2 class="h6 font-bold">تناقضات التواريخ ({{ count($report['date_conflicts'] ?? []) }})</h2>
                    <div class="table-responsive" style="max-height:420px;overflow:auto;">
                        <table class="table table-sm">
                            <tbody>
                            @forelse(($report['date_conflicts'] ?? []) as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('people.edit', $row['person_id']) }}">#{{ $row['person_id'] }} {{ $row['name'] }}</a>
                                        <div class="text-danger small">{{ $row['issue'] }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-muted">لا توجد مشاكل.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm border-0 rounded-xl h-100">
                <div class="card-body">
                    <h2 class="h6 font-bold">بيانات ناقصة ({{ count($report['missing_data'] ?? []) }})</h2>
                    <div class="table-responsive" style="max-height:420px;overflow:auto;">
                        <table class="table table-sm">
                            <tbody>
                            @forelse(($report['missing_data'] ?? []) as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('people.edit', $row['person_id']) }}">#{{ $row['person_id'] }} {{ $row['name'] }}</a>
                                        <div class="small text-muted">{{ implode('، ', $row['missing'] ?? []) }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td class="text-muted">لا توجد عناصر.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm border-0 rounded-xl h-100">
                <div class="card-body">
                    <h2 class="h6 font-bold">مرشحو التكرار ({{ count($report['duplicate_candidates'] ?? []) }})</h2>
                    <div style="max-height:420px;overflow:auto;">
                        @forelse(($report['duplicate_candidates'] ?? []) as $row)
                            <div class="border rounded p-2 mb-2">
                                <div class="small mb-2">
                                    <a href="{{ route('people.edit', $row['a']['id']) }}">#{{ $row['a']['id'] }} {{ $row['a']['name'] }}</a>
                                    ↔
                                    <a href="{{ route('people.edit', $row['b']['id']) }}">#{{ $row['b']['id'] }} {{ $row['b']['name'] }}</a>
                                    <div class="text-muted">{{ $row['reason'] }}</div>
                                </div>
                                <form method="post" action="{{ route('dashboard.ai.data-quality.merge') }}"
                                      onsubmit="return confirm('دمج السجل الثاني في الأول وحذف المكرر؟');">
                                    @csrf
                                    <input type="hidden" name="keep_id" value="{{ $row['a']['id'] }}">
                                    <input type="hidden" name="remove_id" value="{{ $row['b']['id'] }}">
                                    <button class="btn btn-outline-danger btn-sm">دمج (الإبقاء على الأول)</button>
                                </form>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">لا مرشحين حالياً.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
