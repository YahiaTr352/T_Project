<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Jobs\ScanDataQualityJob;
use App\Models\Person;
use App\Services\AI\DataQualityScanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AiDataQualityController extends Controller
{
    public function index(): View
    {
        $report = Cache::get('ai.data_quality.report', [
            'date_conflicts' => [],
            'missing_data' => [],
            'duplicate_candidates' => [],
            'scanned_at' => null,
        ]);

        return view('dashboard.ai.data-quality', compact('report'));
    }

    public function scan(Request $request, DataQualityScanner $scanner): RedirectResponse
    {
        if ($request->boolean('sync')) {
            Cache::put('ai.data_quality.report', $scanner->scan(), now()->addDay());
        } else {
            ScanDataQualityJob::dispatch();
        }

        return redirect()
            ->route('dashboard.ai.data-quality')
            ->with('success', $request->boolean('sync') ? 'اكتمل الفحص.' : 'تم جدولة الفحص في الطابور.');
    }

    public function merge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'keep_id' => 'required|integer|exists:persons,id',
            'remove_id' => 'required|integer|different:keep_id|exists:persons,id',
        ]);

        $keep = Person::query()->findOrFail($validated['keep_id']);
        $remove = Person::query()->findOrFail($validated['remove_id']);

        // Conservative merge: move children / stories ownership pointers only when empty on keep
        Person::query()->where('parent_id', $remove->id)->update(['parent_id' => $keep->id]);
        Person::query()->where('mother_id', $remove->id)->update(['mother_id' => $keep->id]);

        if (! filled($keep->biography) && filled($remove->biography)) {
            $keep->biography = $remove->biography;
        }
        if (! $keep->birth_date && $remove->birth_date) {
            $keep->birth_date = $remove->birth_date;
        }
        if (! $keep->death_date && $remove->death_date) {
            $keep->death_date = $remove->death_date;
        }
        $keep->save();

        $remove->delete();

        activity()
            ->causedBy($request->user())
            ->withProperties(['keep_id' => $keep->id, 'removed_id' => $validated['remove_id']])
            ->log('ai.data_quality.merge');

        Cache::forget('ai.data_quality.report');

        return redirect()
            ->route('dashboard.ai.data-quality')
            ->with('success', "تم دمج السجل #{$validated['remove_id']} في #{$keep->id}.");
    }
}
