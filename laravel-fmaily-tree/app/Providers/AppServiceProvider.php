<?php

namespace App\Providers;

use App\Models\ImportantLink;
use App\Models\RentalRequest;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \App\Services\FaceRecognitionServiceInterface::class,
            \App\Services\AwsFaceRecognitionService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

        // Register Person & Marriage model observers for background face indexing, AI embeddings & cache invalidation
        \App\Models\Person::observe(\App\Observers\PersonObserver::class);
        \App\Models\Marriage::observe(\App\Observers\MarriageObserver::class);
        \App\Models\Story::observe(\App\Observers\StoryObserver::class);
        \App\Models\Article::observe(\App\Observers\ArticleObserver::class);

        // Share pending counts with sidebars (استعارة، روابط مهمة، دعوات، تعديلات الملفات بانتظار الموافقة)
        View::composer(['layouts.sidebar', 'layouts.dashboard.sidebar', 'layouts.dashboard.navbar'], function ($view) {
            $pendingRentalRequestsCount = RentalRequest::where('status', 'pending')->count();
            $pendingImportantLinksCount = ImportantLink::where('status', 'pending')->count();
            $pendingInvitationsCount = \App\Models\Notification::where('status', 'pending_approval')->count();
            $pendingProfileUpdatesCount = \App\Models\ProfileUpdateRequest::where('status', 'pending')->count();
            $view->with([
                'pendingRentalRequestsCount' => $pendingRentalRequestsCount,
                'pendingImportantLinksCount' => $pendingImportantLinksCount,
                'pendingInvitationsCount' => $pendingInvitationsCount,
                'pendingProfileUpdatesCount' => $pendingProfileUpdatesCount,
            ]);
        });
    }
}
