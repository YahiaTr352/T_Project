<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LogHumanizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Models\Audit;
use Spatie\Activitylog\Models\Activity;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $allowedTabs = $this->allowedTabs();
        abort_if(empty($allowedTabs), 403);

        $requestedTab = $request->input('tab', $allowedTabs[0]);
        abort_unless(in_array($requestedTab, $allowedTabs, true), 403);

        $tab = $requestedTab;
        $query = $this->buildQuery($tab, $request);

        $stats = $this->aggregateStats(clone $query);
        $records = $query->latest()->paginate(20)->withQueryString();

        $rows = $records->getCollection()->map(
            fn ($record) => $this->normalizeRow($record, $tab)
        );

        return view('dashboard.logs.index', [
            'tab'          => $tab,
            'allowedTabs'  => $allowedTabs,
            'records'      => $records,
            'rows'         => $rows,
            'stats'        => $stats,
            'filterOptions' => $this->filterOptions($tab),
            'events'       => LogHumanizer::EVENTS,
        ]);
    }

    /**
     * @return list<string>
     */
    private function allowedTabs(): array
    {
        $user = auth()->user();
        $tabs = [];

        if ($user->can('logs.view') || $user->can('system.logs.view')) {
            $tabs[] = 'activity';
        }

        if ($user->can('audit.view')) {
            $tabs[] = 'audits';
        }

        return $tabs;
    }

    private function buildQuery(string $tab, Request $request): Builder
    {
        if ($tab === 'activity') {
            return $this->applyActivityFilters(
                Activity::query()->with(['causer:id,name,email', 'subject']),
                $request
            );
        }

        return $this->applyAuditFilters(
            Audit::query()->with(['user:id,name,email', 'auditable']),
            $request
        );
    }

    private function applyActivityFilters(Builder $query, Request $request): Builder
    {
        $query->when(
            $request->filled('user_id'),
            fn (Builder $q) => $q->where('causer_id', $request->integer('user_id'))
        );

        $query->when(
            $request->filled('subject_type'),
            fn (Builder $q) => $q->where('subject_type', $request->string('subject_type'))
        );

        $query->when(
            $request->filled('event'),
            fn (Builder $q) => $q->where('event', $request->string('event'))
        );

        $query->when(
            $request->filled('request_id'),
            fn (Builder $q) => $q->where('properties->request_id', $request->string('request_id'))
        );

        if ($request->filled(['from', 'to'])) {
            $from = Carbon::parse($request->input('from'))->startOfDay();
            $to = Carbon::parse($request->input('to'))->endOfDay();
            $query->whereBetween('created_at', [$from, $to]);
        }

        return $query;
    }

    private function applyAuditFilters(Builder $query, Request $request): Builder
    {
        $query->when(
            $request->filled('user_id'),
            fn (Builder $q) => $q->where('user_id', $request->integer('user_id'))
        );

        $query->when(
            $request->filled('auditable_type'),
            fn (Builder $q) => $q->where('auditable_type', $request->string('auditable_type'))
        );

        $query->when(
            $request->filled('event'),
            fn (Builder $q) => $q->where('event', $request->string('event'))
        );

        if ($request->filled(['from', 'to'])) {
            $from = Carbon::parse($request->input('from'))->startOfDay();
            $to = Carbon::parse($request->input('to'))->endOfDay();
            $query->whereBetween('created_at', [$from, $to]);
        }

        return $query;
    }

    /**
     * @return array{total: int, created: int, updated: int, deleted: int, restored: int}
     */
    private function aggregateStats(Builder $query): array
    {
        $counts = (clone $query)
            ->reorder()
            ->selectRaw('event, COUNT(*) as count')
            ->groupBy('event')
            ->pluck('count', 'event');

        return [
            'total'    => (int) $counts->sum(),
            'created'  => (int) ($counts['created'] ?? 0),
            'updated'  => (int) ($counts['updated'] ?? 0),
            'deleted'  => (int) ($counts['deleted'] ?? 0),
            'restored' => (int) ($counts['restored'] ?? 0),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     tab: string,
     *     time: string,
     *     time_human: string,
     *     actor_name: ?string,
     *     actor_id: ?int,
     *     event: string,
     *     event_label: string,
     *     subject_label: string,
     *     subject_class: string,
     *     subject_id: ?int,
     *     summary: string,
     *     description: ?string,
     *     diff_rows: array,
     *     has_diff: bool,
     *     meta: array{ip: ?string, user_agent: ?string, request_id: ?string, geo: array}
     * }
     */
    private function normalizeRow(Activity|Audit $record, string $tab): array
    {
        if ($tab === 'activity') {
            /** @var Activity $record */
            $properties = ($record->properties ?? collect())->toArray();
            $changes = $record->changes ?? [];
            $oldValues = $changes['old'] ?? [];
            $newValues = $changes['attributes'] ?? [];
            $event = $record->event ?? $record->description ?? 'updated';
            $who = $record->causer?->name;
            $subjectLabel = LogHumanizer::subjectLabel($record->subject_type, $record->subject_id);
            $lines = LogHumanizer::lines($oldValues, $newValues);
            $diffRows = LogHumanizer::changeRows($oldValues, $newValues);
            $ip = $properties['ip'] ?? null;

            return [
                'id'             => $record->id,
                'tab'            => 'activity',
                'time'           => $record->created_at->format('Y-m-d H:i'),
                'time_human'     => $record->created_at->diffForHumans(),
                'actor_name'     => $who,
                'actor_id'       => $record->causer_id,
                'event'          => $event,
                'event_label'    => LogHumanizer::eventLabel($event),
                'subject_label'  => $subjectLabel,
                'subject_class'  => class_basename((string) $record->subject_type),
                'subject_id'     => $record->subject_id,
                'summary'        => $lines[0] ?? LogHumanizer::title($who, $event, $subjectLabel),
                'description'    => $record->description,
                'diff_rows'      => $diffRows,
                'has_diff'       => !empty($diffRows),
                'meta'           => [
                    'ip'         => $ip,
                    'user_agent' => $properties['user_agent'] ?? null,
                    'request_id' => $properties['request_id'] ?? null,
                    'geo'        => $ip ? LogHumanizer::getLocationFromIp($ip) : [],
                ],
            ];
        }

        /** @var Audit $record */
        $oldValues = $record->old_values ?? [];
        $newValues = $record->new_values ?? [];
        $event = $record->event ?? 'updated';
        $who = $record->user?->name;
        $subjectLabel = LogHumanizer::subjectLabel($record->auditable_type, $record->auditable_id);
        $lines = LogHumanizer::lines($oldValues, $newValues);
        $diffRows = LogHumanizer::changeRows($oldValues, $newValues);
        $ip = $record->ip_address;

        return [
            'id'             => $record->id,
            'tab'            => 'audits',
            'time'           => $record->created_at->format('Y-m-d H:i:s'),
            'time_human'     => $record->created_at->diffForHumans(),
            'actor_name'     => $who,
            'actor_id'       => $record->user_id,
            'event'          => $event,
            'event_label'    => LogHumanizer::eventLabel($event),
            'subject_label'  => $subjectLabel,
            'subject_class'  => class_basename((string) $record->auditable_type),
            'subject_id'     => $record->auditable_id,
            'summary'        => $lines[0] ?? LogHumanizer::title($who, $event, $subjectLabel),
            'description'    => null,
            'diff_rows'      => $diffRows,
            'has_diff'       => !empty($diffRows),
            'meta'           => [
                'ip'         => $ip,
                'user_agent' => $record->user_agent,
                'request_id' => null,
                'geo'        => $ip ? LogHumanizer::getLocationFromIp($ip) : [],
            ],
        ];
    }

    /**
     * @return array{users: \Illuminate\Support\Collection, types: \Illuminate\Support\Collection, events: array}
     */
    private function filterOptions(string $tab): array
    {
        if ($tab === 'activity') {
            $userIds = Activity::query()->whereNotNull('causer_id')->distinct()->pluck('causer_id');
            $types = Activity::query()->select('subject_type')->distinct()->pluck('subject_type')->filter();
            $events = Activity::query()->select('event')->distinct()->pluck('event')->filter();
        } else {
            $userIds = Audit::query()->whereNotNull('user_id')->distinct()->pluck('user_id');
            $types = Audit::query()->select('auditable_type')->distinct()->pluck('auditable_type')->filter();
            $events = Audit::query()->select('event')->distinct()->pluck('event')->filter();
        }

        $users = User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->pluck('name', 'id');

        $typeOptions = $types->mapWithKeys(function ($type) {
            $label = LogHumanizer::subjectLabel($type, 0);

            return [$type => preg_replace('/\s*#\d+$/', '', $label) ?: class_basename($type)];
        });

        return [
            'users'  => $users,
            'types'  => $typeOptions,
            'events' => $events,
        ];
    }
}
