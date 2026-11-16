<?php

namespace App\Support;

/**
 * Default permissions merged into roles when seeding or migrating.
 * Manually assigned permissions are always preserved (merge, never replace).
 */
class RolePermissionBaselines
{
    public static function editor(): array
    {
        return [
            // Family
            'people.view', 'people.create', 'people.update',
            'marriages.view', 'marriages.create', 'marriages.update',
            'breastfeeding.view', 'breastfeeding.create', 'breastfeeding.update',
            'friendships.view', 'friendships.create', 'friendships.update', 'friendships.delete',
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',

            // Content & media
            'articles.view', 'articles.create', 'articles.update',
            'categories.view', 'categories.create', 'categories.update',
            'stories.view', 'stories.create', 'stories.update', 'stories.delete',
            'images.view', 'images.upload', 'images.update', 'images.delete',
            'padges.view', 'padges.create', 'padges.update',

            // Site content
            'site-content.view', 'site-content.create', 'site-content.update', 'site-content.delete',
            'slideshow.view', 'slideshow.create', 'slideshow.update', 'slideshow.delete',
            'home-gallery.view', 'home-gallery.create', 'home-gallery.update', 'home-gallery.delete',
            'courses.view', 'courses.create', 'courses.update', 'courses.delete',
            'programs.view', 'programs.create', 'programs.update', 'programs.delete',

            // Products
            'products.view', 'products.create', 'products.update', 'products.delete',

            // Councils & activities
            'councils.view', 'councils.create', 'councils.update', 'councils.delete',
            'walking-program.view', 'walking-program.create', 'walking-program.update', 'walking-program.delete',

            // Notifications
            'notifications.view', 'notifications.send', 'notifications.manage-groups',

            // Chat moderation (view, messages & participants/groups)
            'chat.moderation.view', 'chat.moderation.messages', 'chat.moderation.participants',

            // Logs & analytics
            'logs.view', 'system.logs.view', 'audit.view',
            'visit-logs.view',

            // Dashboard
            'dashboard.view', 'dashboard.stats',
        ];
    }

    public static function viewer(): array
    {
        return [
            'people.view',
            'marriages.view',
            'breastfeeding.view',
            'articles.view',
            'categories.view',
            'padges.view',
            'images.view',
            'dashboard.view',
            'walking-program.view',
            'notifications.view',
            'chat.moderation.view',
        ];
    }

    public static function user(): array
    {
        return [
            'people.view',
            'articles.view',
            'categories.view',
            'padges.view',
            'images.view',
            'dashboard.view',
            'walking-program.view',
            'notifications.view',
        ];
    }
}
