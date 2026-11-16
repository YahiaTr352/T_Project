/**
 * ====================================================================
 * Dashboard Interactions & Sidebar UX Enhancements
 * Family Tree App - Al-Sorayia
 * ====================================================================
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    var $body = $('body');
    var $sidebar = $('.sidebar-left');

    // 1. Initial State Restoration for Desktop
    function initSidebarState() {
        if (window.innerWidth >= 992) {
            var isClosed = localStorage.getItem('sidebar_closed') === '1';
            if (isClosed) {
                $body.addClass('sidebar-closed collapsed');
            } else {
                $body.removeClass('sidebar-closed collapsed');
            }
            $body.removeClass('sidebar-open');
        } else {
            // Mobile always starts closed
            $body.removeClass('sidebar-closed collapsed sidebar-open');
        }
    }
    initSidebarState();

    // 2. Remove any lingering direct listeners on .collapseSidebar elements
    $('.collapseSidebar').off('click');

    // 3. Unified Sidebar Toggle Handler (Desktop slide-out close & Mobile off-canvas drawer)
    $(document).off('click', '.collapseSidebar').on('click', '.collapseSidebar', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (window.innerWidth < 992) {
            // Mobile: Toggle off-canvas drawer
            $body.toggleClass('sidebar-open');
        } else {
            // Desktop: Toggle sidebar open / closed
            var isCurrentlyClosed = $body.hasClass('sidebar-closed') || $body.hasClass('collapsed');
            if (isCurrentlyClosed) {
                // Open sidebar
                $body.removeClass('sidebar-closed collapsed');
                localStorage.setItem('sidebar_closed', '0');
            } else {
                // Close sidebar completely
                $body.addClass('sidebar-closed collapsed');
                localStorage.setItem('sidebar_closed', '1');
            }
        }
    });

    // 4. Close mobile sidebar when clicking backdrop or close button
    $(document).on('click', '#sidebarBackdrop, #sidebarCloseBtn', function (e) {
        e.preventDefault();
        $body.removeClass('sidebar-open');
    });

    // 5. Close mobile sidebar when navigating via sub-menu links
    $sidebar.on('click', 'a.sub-nav-link', function () {
        if (window.innerWidth < 992) {
            $body.removeClass('sidebar-open');
        }
    });

    // 6. Close mobile sidebar on Escape key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $body.hasClass('sidebar-open')) {
            $body.removeClass('sidebar-open');
        }
    });

    // 7. Handle viewport resize smoothly
    var resizeTimer;
    $(window).on('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (window.innerWidth >= 992) {
                $body.removeClass('sidebar-open');
                if (localStorage.getItem('sidebar_closed') === '1') {
                    $body.addClass('sidebar-closed collapsed');
                } else {
                    $body.removeClass('sidebar-closed collapsed');
                }
            } else {
                $body.removeClass('sidebar-closed collapsed');
            }
        }, 150);
    });

    // 8. Auto-scroll sidebar to active element if needed
    try {
        var activeItem = document.querySelector('.sidebar-left .sub-nav-list .nav-item.active, .sidebar-left .navbar-nav > .nav-item.active');
        if (activeItem) {
            setTimeout(function () {
                activeItem.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
            }, 250);
        }
    } catch (e) {
        // Silently ignore if not supported
    }

    // 9. Enhance Bootstrap collapse chevrons & accessibility
    var collapseElements = document.querySelectorAll('.sidebar-left .collapse');
    collapseElements.forEach(function (collapseEl) {
        var parentToggle = document.querySelector('.sidebar-left [data-toggle="collapse"][href="#' + collapseEl.id + '"]');
        if (!parentToggle) return;

        $(collapseEl).on('show.bs.collapse', function () {
            parentToggle.setAttribute('aria-expanded', 'true');
            parentToggle.classList.remove('collapsed');
        });

        $(collapseEl).on('hide.bs.collapse', function () {
            parentToggle.setAttribute('aria-expanded', 'false');
            parentToggle.classList.add('collapsed');
        });
    });
});
