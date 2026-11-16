<?php

use App\Http\Controllers\Api\BreastfeedingPublicApiController;
use App\Http\Controllers\Api\ReportsPublicApiController;
use App\Http\Controllers\Api\SitePasswordApiController;
use App\Http\Controllers\Api\FaceSearchController;
use App\Http\Controllers\Api\GalleryFaceSearchController;
use App\Http\Controllers\FamilyTreeController;
use App\Http\Controllers\GalleryApiController;
use App\Http\Controllers\HomeApiController;
use App\Http\Controllers\PersonsBadgesApiController;
use App\Http\Controllers\ProgramShowApiController;
use App\Http\Controllers\QuranCompetitionApiController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\StoreApiController;
use App\Http\Controllers\StoriesPublicController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (مجموعة middleware "api" من RouteServiceProvider + prefix api)
|--------------------------------------------------------------------------
|
| مسارات الجوال الخالية من الجلسة (site-password، sanctum) خارج مجموعة web.
| مسارات JSON العامة (شجرة العائلة، التقارير، الصفحة الرئيسية API، …)
| تُلف بـ middleware "web" داخل المجموعة أدناه ليبقى StartSession و
| SitePasswordProtection كما عندما كانت في web.php (مهم لصفحة /sila).
| صفحة HTML لصلة تبقى في routes/web.php.
|
*/

use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Api\V1\InvitationApiController;
use App\Http\Controllers\Api\V1\MyProfileApiController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('v1/auth')->name('api.v1.auth.')->group(function () {
    $sessionStack = [
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
    ];

    Route::post('face-verify', [AuthApiController::class, 'faceVerify'])
        ->middleware(array_merge(['throttle:10,1'], $sessionStack))
        ->name('face-verify');
    Route::post('face-verify/confirm-otp', [AuthApiController::class, 'confirmFaceOtp'])
        ->middleware(array_merge(['throttle:15,1'], $sessionStack))
        ->name('face-verify.confirm-otp');
    Route::post('face-verify/resend-otp', [AuthApiController::class, 'resendFaceOtp'])
        ->middleware(array_merge(['throttle:5,1'], $sessionStack))
        ->name('face-verify.resend-otp');
    Route::post('whatsapp/send-otp', [AuthApiController::class, 'sendWhatsappOtp'])
        ->middleware(array_merge(['throttle:10,1'], $sessionStack))
        ->name('whatsapp.send-otp');
    Route::post('whatsapp/verify-otp', [AuthApiController::class, 'verifyWhatsappOtp'])
        ->middleware(array_merge(['throttle:15,1'], $sessionStack))
        ->name('whatsapp.verify-otp');
    Route::post('child/parent-lookup', [AuthApiController::class, 'childParentLookup'])
        ->middleware(array_merge(['throttle:15,1'], $sessionStack))
        ->name('child.parent-lookup');
    Route::post('child/send-otp', [AuthApiController::class, 'sendChildWhatsappOtp'])
        ->middleware(array_merge(['throttle:10,1'], $sessionStack))
        ->name('child.send-otp');
    Route::post('child/verify-otp', [AuthApiController::class, 'verifyChildWhatsappOtp'])
        ->middleware(array_merge(['throttle:15,1'], $sessionStack))
        ->name('child.verify-otp');
    Route::post('refresh', [AuthApiController::class, 'refreshToken'])
        ->middleware('throttle:30,1')
        ->name('refresh');
    Route::get('me', [AuthApiController::class, 'me'])->name('me');
});

$invitationIdentityStack = [
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\EnsureInvitationIdentity::class,
];

Route::prefix('v1/invitations')->name('api.v1.invitations.')->middleware($invitationIdentityStack)->group(function () {
    Route::get('/templates', [InvitationApiController::class, 'templates'])->name('templates');
    Route::get('/tree-picker', [InvitationApiController::class, 'treePicker'])->name('tree-picker');
    Route::get('/persons', [InvitationApiController::class, 'searchPersons'])->name('persons');
    Route::get('/groups', [InvitationApiController::class, 'groupsIndex'])->name('groups.index');
    Route::post('/groups', [InvitationApiController::class, 'groupsStore'])->name('groups.store');
    Route::get('/groups/{group}', [InvitationApiController::class, 'groupsShow'])->name('groups.show');
    Route::put('/groups/{group}', [InvitationApiController::class, 'groupsUpdate'])->name('groups.update');
    Route::delete('/groups/{group}', [InvitationApiController::class, 'groupsDestroy'])->name('groups.destroy');
    Route::post('/groups/{group}/persons', [InvitationApiController::class, 'attachPerson'])->name('groups.attach');
    Route::delete('/groups/{group}/persons/{member}', [InvitationApiController::class, 'detachPerson'])->name('groups.detach');
    Route::post('/preview', [InvitationApiController::class, 'preview'])->middleware('throttle:30,1')->name('preview');
    Route::post('/send', [InvitationApiController::class, 'send'])->middleware('throttle:20,1')->name('send');
    Route::get('/logs', [InvitationApiController::class, 'logs'])->name('logs');
    Route::get('/logs/{notification}', [InvitationApiController::class, 'logShow'])->name('logs.show');
    Route::get('/backgrounds', [\App\Http\Controllers\Api\V1\InvitationBackgroundApiController::class, 'index'])->name('backgrounds.index');
    Route::get('/backgrounds/mine', [\App\Http\Controllers\Api\V1\InvitationBackgroundApiController::class, 'mine'])->name('backgrounds.mine');
    Route::post('/backgrounds', [\App\Http\Controllers\Api\V1\InvitationBackgroundApiController::class, 'store'])->middleware('throttle:20,1')->name('backgrounds.store');
    Route::delete('/backgrounds/{id}', [\App\Http\Controllers\Api\V1\InvitationBackgroundApiController::class, 'destroyMine'])->name('backgrounds.destroy');
    Route::get('/creatives', [\App\Http\Controllers\Api\V1\CreativeTemplateApiController::class, 'index'])->name('creatives.index');
    Route::get('/creatives/mine', [\App\Http\Controllers\Api\V1\CreativeTemplateApiController::class, 'mine'])->name('creatives.mine');
    Route::post('/creatives', [\App\Http\Controllers\Api\V1\CreativeTemplateApiController::class, 'store'])->middleware('throttle:20,1')->name('creatives.store');
    Route::delete('/creatives/{id}', [\App\Http\Controllers\Api\V1\CreativeTemplateApiController::class, 'destroyMine'])->name('creatives.destroy');
});

$profileSessionStack = [
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\EnsureProfileIdentity::class,
];

Route::prefix('v1/me')->name('api.v1.me.')->middleware($profileSessionStack)->group(function () {
    Route::get('/', [MyProfileApiController::class, 'show'])->name('show');
    Route::match(['put', 'post', 'patch'], '/', [MyProfileApiController::class, 'update'])->name('update');
    Route::patch('/privacy', [MyProfileApiController::class, 'updatePrivacy'])->name('privacy.update');
    Route::get('/contacts', [MyProfileApiController::class, 'listContacts'])->name('contacts.index');
    Route::post('/contacts', [MyProfileApiController::class, 'storeContact'])->name('contacts.store');
    Route::put('/contacts/{contact}', [MyProfileApiController::class, 'updateContact'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [MyProfileApiController::class, 'destroyContact'])->name('contacts.destroy');
    Route::post('/children', [MyProfileApiController::class, 'storeChild'])->name('children.store');
    Route::get('/marriages', [MyProfileApiController::class, 'listMarriages'])->name('marriages.index');
    Route::post('/marriages', [MyProfileApiController::class, 'storeMarriage'])->name('marriages.store');
    Route::put('/marriages/{marriage}', [MyProfileApiController::class, 'updateMarriage'])->name('marriages.update');
    Route::get('/locations', [MyProfileApiController::class, 'listLocations'])->name('locations.index');
    Route::post('/locations', [MyProfileApiController::class, 'storeLocation'])->name('locations.store');
    Route::put('/locations/{location}', [MyProfileApiController::class, 'updateLocation'])->name('locations.update');
    Route::delete('/locations/{location}', [MyProfileApiController::class, 'destroyLocation'])->name('locations.destroy');
});

Route::prefix('site-password')->name('api.site-password.')->group(function () {
    Route::get('status', [SitePasswordApiController::class, 'status'])->name('status');
    Route::post('verify', [SitePasswordApiController::class, 'verify'])
        ->middleware('throttle:20,1')
        ->name('verify');
    Route::post('revoke', [SitePasswordApiController::class, 'revoke'])
        ->middleware('throttle:30,1')
        ->name('revoke');
});

$publicWebStack = [
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
];

Route::middleware($publicWebStack)->group(function () {
    Route::get('/reports', [ReportsPublicApiController::class, 'index']);
    Route::get('/reports/person/{personId}/statistics', [ReportsController::class, 'getPersonStatistics'])->name('reports.person.statistics');
    Route::get('/reports/location/{locationId}/persons', [ReportsController::class, 'getLocationPersons'])->name('reports.location.persons');
    Route::get('/reports/name/{name}/persons', [ReportsController::class, 'getPersonsByName'])->name('reports.name.persons');

    Route::get('/home', HomeApiController::class);
    Route::get('/breastfeeding', [BreastfeedingPublicApiController::class, 'index']);
    Route::get('/gallery/categories', [GalleryApiController::class, 'categories']);
    Route::get('/gallery/images', [GalleryApiController::class, 'images']);
    Route::get('/gallery/articles', [GalleryApiController::class, 'articles']);
    Route::get('/gallery/articles/{id}', [GalleryApiController::class, 'showArticle']);
    Route::post('/gallery/search-by-face', [GalleryFaceSearchController::class, 'search'])->name('api.gallery.search-by-face')->middleware('throttle:30,1');

    Route::prefix('store')->group(function () {
        Route::get('/', [StoreApiController::class, 'index']);
        Route::get('/categories/{category}', [StoreApiController::class, 'category']);
        Route::get('/subcategories/{subcategory}', [StoreApiController::class, 'subcategory']);
        Route::get('/products', [StoreApiController::class, 'products']);
        Route::get('/products/{product}', [StoreApiController::class, 'show']);
    });

    Route::prefix('quran-competitions')->group(function () {
        Route::get('/navigation', [QuranCompetitionApiController::class, 'navigation']);
        Route::get('/', [QuranCompetitionApiController::class, 'index']);
        Route::get('/categories/{category}', [QuranCompetitionApiController::class, 'category']);
        Route::get('/{competition}', [QuranCompetitionApiController::class, 'show']);
    });
    Route::get('/persons/badges', PersonsBadgesApiController::class);
    Route::get('/programs/{program}', [ProgramShowApiController::class, 'show']);
    Route::get('/family-tree', [FamilyTreeController::class, 'getFamilyTree']);
    Route::get('/person/{id}', [FamilyTreeController::class, 'getPersonDetails']);
    Route::get('/person/{id}/children', [FamilyTreeController::class, 'getChildren']);
    Route::get('/person/{id}/children-details', [FamilyTreeController::class, 'getChildrenForDetails']);
    Route::get('/person/{father}/wives', [FamilyTreeController::class, 'getWives']);
    Route::get('/person/{id}/stories/count', [StoriesPublicController::class, 'countForPerson']);
    Route::get('/person/{id}/stories', [StoriesPublicController::class, 'getStoriesForPerson']);
    Route::get('/person/{id}/images', [FamilyTreeController::class, 'getPersonImages']);
    Route::get('/person/{id}/friends', [FamilyTreeController::class, 'getPersonFriends']);
    Route::get('/person/{id}/friendships/count', [FamilyTreeController::class, 'getFriendshipsCount']);

    Route::get('/generations', [FamilyTreeController::class, 'getGenerations']);
    Route::get('/generation/{level}/whatsapp', [FamilyTreeController::class, 'getGenerationWithWhatsApp']);
    Route::get('/persons/search', [FamilyTreeController::class, 'searchPersons']);
    Route::post('/persons/search/image', [FaceSearchController::class, 'search']);
    Route::get('/persons/search/whatsapp', [FamilyTreeController::class, 'searchPersonsWithWhatsApp']);
    Route::get('/person/{id}/whatsapp', [FamilyTreeController::class, 'getPersonWithWhatsApp']);
    Route::get('/person/{id}/children/whatsapp', [FamilyTreeController::class, 'getChildrenWithWhatsApp']);
    Route::get('/person/{id}/descendants/whatsapp', [FamilyTreeController::class, 'getDescendantsWithWhatsApp']);

    // Quiz Competition API Routes for Mobile
    Route::prefix('v1/quiz-competitions')->group(function () {
        Route::get('/active', [\App\Http\Controllers\Api\QuizCompetitionApiController::class, 'getActiveCompetition']);
        Route::get('/candidates/tree-nodes', [\App\Http\Controllers\Api\QuizCompetitionApiController::class, 'candidateTreeNodes']);
        Route::get('/walking-race/{question}', [\App\Http\Controllers\Api\QuizCompetitionApiController::class, 'walkingRaceTimeline']);
        Route::post('/{quizCompetition}/questions/{quizQuestion}/answer', [\App\Http\Controllers\Api\QuizCompetitionApiController::class, 'storeAnswer'])
            ->middleware('throttle:5,1');
        Route::get('/{quizCompetition}/questions/{quizQuestion}/vote-results', [\App\Http\Controllers\Api\QuizCompetitionApiController::class, 'getVoteResults']);

        // Question Live Discussion / Polymorphic Chat
        Route::prefix('questions/{question}/chat')->name('questions.chat.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\QuizQuestionChatApiController::class, 'show'])->name('show');
            Route::get('/messages', [\App\Http\Controllers\Api\V1\QuizQuestionChatApiController::class, 'messages'])
                ->middleware('throttle:walking-chat-messages')
                ->name('messages');
            Route::post('/messages', [\App\Http\Controllers\Api\V1\QuizQuestionChatApiController::class, 'sendMessage'])
                ->middleware('throttle:walking-chat-send')
                ->name('send');
            Route::post('/read', [\App\Http\Controllers\Api\V1\QuizQuestionChatApiController::class, 'markAsRead'])->name('read');
            Route::post('/mute', [\App\Http\Controllers\Api\V1\QuizQuestionChatApiController::class, 'toggleMute'])->name('mute');
            Route::put('/messages/{messageId}', [\App\Http\Controllers\Api\V1\QuizQuestionChatApiController::class, 'updateMessage'])
                ->whereNumber('messageId')
                ->name('update');
            Route::delete('/messages/{messageId}', [\App\Http\Controllers\Api\V1\QuizQuestionChatApiController::class, 'destroyMessage'])
                ->whereNumber('messageId')
                ->name('destroy');
            Route::post('/clear', [\App\Http\Controllers\Api\V1\QuizQuestionChatApiController::class, 'clearChat'])
                ->name('clear');
        });
    });

    // Mobile Notification Inbox & Device registration API Routes
    Route::post('/devices/register', [\App\Http\Controllers\Api\MobileNotificationApiController::class, 'register']);
    Route::get('/notifications', [\App\Http\Controllers\Api\MobileNotificationApiController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\MobileNotificationApiController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\MobileNotificationApiController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\MobileNotificationApiController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\MobileNotificationApiController::class, 'destroy']);
    Route::post('/notifications/delete-multiple', [\App\Http\Controllers\Api\MobileNotificationApiController::class, 'deleteMultiple']);
    Route::post('/notifications/clear-all', [\App\Http\Controllers\Api\MobileNotificationApiController::class, 'clearAll']);

    // Kinship & LCA API Route
    Route::get('/v1/kinship/calculate', [\App\Http\Controllers\Api\KinshipController::class, 'calculate'])
        ->middleware('throttle:30,1');

    // AI assistant API for mobile app
    Route::prefix('v1/ai')->name('api.v1.ai.')->middleware('throttle:30,1')->group(function () {
        Route::post('/chat', [\App\Http\Controllers\Api\V1\AiApiController::class, 'chat'])->name('chat');
        Route::post('/chat/stream', [\App\Http\Controllers\Api\V1\AiApiController::class, 'chatStream'])->name('chat.stream');
        Route::post('/voice-chat', [\App\Http\Controllers\Api\V1\AiApiController::class, 'voiceChat'])->name('voice-chat');
        Route::post('/photo-chat', [\App\Http\Controllers\Api\V1\AiApiController::class, 'photoChat'])->name('photo-chat');
        Route::post('/tts', [\App\Http\Controllers\Api\V1\AiApiController::class, 'tts'])->name('tts');
        Route::get('/conversations', [\App\Http\Controllers\Api\V1\AiApiController::class, 'conversations'])->name('conversations');
        Route::get('/conversations/{uuid}', [\App\Http\Controllers\Api\V1\AiApiController::class, 'showConversation'])->name('conversations.show');
        Route::get('/suggestions', [\App\Http\Controllers\Api\V1\AiApiController::class, 'suggestions'])->name('suggestions');
        Route::get('/search', [\App\Http\Controllers\Api\V1\AiApiController::class, 'search'])->name('search');
    });

    // â”€â”€â”€ Walking & Step Tracking API â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    Route::prefix('v1/walking')->name('api.v1.walking.')->group(function () {
        // Step data sync
        Route::post('/sync', [\App\Http\Controllers\Api\V1\WalkingApiController::class, 'sync'])
            ->middleware('throttle:walking-sync')
            ->name('sync');
        Route::post('/batch-sync', [\App\Http\Controllers\Api\V1\WalkingApiController::class, 'batchSync'])
            ->middleware('throttle:walking-sync')
            ->name('batch-sync');

        // Active walking clubs configuration (Dynamic clubs, steps, colors, icons & calculation start date)
        Route::get('/clubs', [\App\Http\Controllers\Api\V1\WalkingApiController::class, 'clubs'])
            ->name('clubs');

        // Leaderboard & stats (covered by identity-aware global api throttle)
        Route::get('/leaderboard', [\App\Http\Controllers\Api\V1\WalkingApiController::class, 'leaderboard'])
            ->name('leaderboard');
        Route::get('/leaderboard/streak', [\App\Http\Controllers\Api\V1\WalkingApiController::class, 'streakLeaderboard'])
            ->name('leaderboard.streak');
        Route::get('/privacy-settings', [\App\Http\Controllers\Api\V1\WalkingApiController::class, 'getPrivacySettings'])
            ->name('privacy-settings.get');
        Route::post('/privacy-settings', [\App\Http\Controllers\Api\V1\WalkingApiController::class, 'updatePrivacySettings'])
            ->name('privacy-settings.update');
        Route::get('/my-stats', [\App\Http\Controllers\Api\V1\WalkingApiController::class, 'myStats'])
            ->name('my-stats');
        Route::get('/history', [\App\Http\Controllers\Api\V1\WalkingApiController::class, 'history'])
            ->name('history');

        // Groups / Challenges
        Route::get('/groups', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'index'])
            ->name('groups.index');
        Route::post('/groups', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'store'])
            ->middleware('throttle:walking-group-store')
            ->name('groups.store');
        Route::post('/groups/join-code', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'joinByCode'])
            ->middleware('throttle:walking-group-actions')
            ->name('groups.join-code');
        Route::get('/groups/{group}', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'show'])
            ->name('groups.show');
        Route::put('/groups/{group}', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'update'])
            ->middleware('throttle:walking-group-actions')
            ->name('groups.update');
        Route::delete('/groups/{group}', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'destroy'])
            ->name('groups.destroy');
        Route::post('/groups/{groupId}/restore', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'restore'])
            ->whereNumber('groupId')
            ->name('groups.restore');

        // Group membership
        Route::post('/groups/{group}/join', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'join'])
            ->middleware('throttle:walking-group-actions')
            ->name('groups.join');
        Route::post('/groups/{group}/leave', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'leave'])
            ->middleware('throttle:walking-group-actions')
            ->name('groups.leave');
        Route::post('/groups/{group}/members', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'inviteMember'])
            ->middleware('throttle:walking-group-actions')
            ->name('groups.members.invite');
        Route::delete('/groups/{group}/members/{targetPersonId}', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'removeMember'])
            ->name('groups.members.remove');

        // Group privacy settings & reactivation
        Route::get('/groups/{group}/member-settings', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'getMemberSettings'])
            ->name('groups.member-settings.show');
        Route::patch('/groups/{group}/member-settings', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'updateMemberSettings'])
            ->middleware('throttle:walking-group-actions')
            ->name('groups.member-settings.update');
        Route::post('/groups/{group}/reactivate', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'reactivate'])
            ->middleware('throttle:walking-group-actions')
            ->name('groups.reactivate');

        // Group-scoped leaderboard
        Route::get('/groups/{group}/leaderboard', [\App\Http\Controllers\Api\V1\WalkingGroupApiController::class, 'leaderboard'])
            ->name('groups.leaderboard');

        // Group Chat / Discussion
        Route::get('/groups/{group}/chat', [\App\Http\Controllers\Api\V1\WalkingGroupChatApiController::class, 'show'])
            ->name('groups.chat.show');
        Route::get('/groups/{group}/chat/messages', [\App\Http\Controllers\Api\V1\WalkingGroupChatApiController::class, 'messages'])
            ->middleware('throttle:walking-chat-messages')
            ->name('groups.chat.messages');
        Route::post('/groups/{group}/chat/messages', [\App\Http\Controllers\Api\V1\WalkingGroupChatApiController::class, 'sendMessage'])
            ->middleware('throttle:walking-chat-send')
            ->name('groups.chat.messages.send');
        Route::post('/groups/{group}/chat/read', [\App\Http\Controllers\Api\V1\WalkingGroupChatApiController::class, 'markAsRead'])
            ->name('groups.chat.read');
        Route::post('/groups/{group}/chat/mute', [\App\Http\Controllers\Api\V1\WalkingGroupChatApiController::class, 'toggleMute'])
            ->name('groups.chat.mute');
        Route::put('/groups/{group}/chat/messages/{messageId}', [\App\Http\Controllers\Api\V1\WalkingGroupChatApiController::class, 'updateMessage'])
            ->whereNumber('messageId')
            ->name('groups.chat.messages.update');
        Route::delete('/groups/{group}/chat/messages/{messageId}', [\App\Http\Controllers\Api\V1\WalkingGroupChatApiController::class, 'destroyMessage'])
            ->whereNumber('messageId')
            ->name('groups.chat.messages.destroy');
        Route::post('/groups/{group}/chat/clear', [\App\Http\Controllers\Api\V1\WalkingGroupChatApiController::class, 'clearChat'])
            ->name('groups.chat.clear');

        // ── Virtual Trek & Saudi Tour Routes ─────────────────────────
        Route::get('/treks/active', [\App\Http\Controllers\Api\V1\WalkingTrekApiController::class, 'active'])
            ->name('treks.active');
        Route::get('/treks/contributors', [\App\Http\Controllers\Api\V1\WalkingTrekApiController::class, 'contributors'])
            ->name('treks.contributors');
        Route::get('/treks/{trek}/contributors', [\App\Http\Controllers\Api\V1\WalkingTrekApiController::class, 'contributors'])
            ->name('treks.show.contributors');
        Route::get('/treks/{trek}', [\App\Http\Controllers\Api\V1\WalkingTrekApiController::class, 'show'])
            ->name('treks.show');
    });

    // ─── Chat Moderation (Reports & Status) ─────────────────────────────
    Route::prefix('v1/chat')->name('api.v1.chat.')->group(function () {
        Route::post('/messages/{messageId}/report', [\App\Http\Controllers\Api\V1\ChatModerationApiController::class, 'reportMessage'])
            ->whereNumber('messageId')
            ->middleware('throttle:15,1')
            ->name('messages.report');

        Route::delete('/messages/{messageId}', [\App\Http\Controllers\Api\V1\ChatModerationApiController::class, 'destroyMessage'])
            ->whereNumber('messageId')
            ->name('messages.destroy');

        Route::get('/conversations/{conversation}/status', [\App\Http\Controllers\Api\V1\ChatModerationApiController::class, 'conversationStatus'])
            ->name('conversations.status');

        Route::post('/conversations/{conversation}/clear', [\App\Http\Controllers\Api\V1\ChatModerationApiController::class, 'clearConversation'])
            ->name('conversations.clear');
    });
});

Route::prefix('v1/app')->name('api.v1.app.')->group(function () {
    Route::post('check-version', [\App\Http\Controllers\Api\AppVersionApiController::class, 'checkVersion'])
        ->middleware('throttle:120,1')
        ->name('check-version');

    Route::get('settings', [\App\Http\Controllers\Api\AppVersionApiController::class, 'bootstrapSettings'])
        ->middleware('throttle:60,1')
        ->name('settings');
});

// ─── Contact Messages & Inquiries (Mobile App) ───────────────────────────
Route::prefix('v1/contact-messages')->name('api.v1.contact-messages.')->group(function () {
    Route::post('/', [\App\Http\Controllers\Api\V1\ContactMessageApiController::class, 'store'])
        ->middleware('throttle:15,1')
        ->name('store');
});


