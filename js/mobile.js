/**
 * Haven Hotel - Dynamic Mobile & Responsive Navigation Script
 */
(function() {
    'use strict';

    function initMobileNav() {
        const toggleBtn = document.getElementById('mobile-menu-toggle');
        const navMenu = document.getElementById('primary-nav');
        const overlay = document.getElementById('nav-overlay');
        const body = document.body;

        if (!toggleBtn || !navMenu) return;

        function openMenu() {
            toggleBtn.classList.add('active');
            toggleBtn.setAttribute('aria-expanded', 'true');
            navMenu.classList.add('open');
            if (overlay) overlay.classList.add('active');
            body.classList.add('mobile-nav-open');
        }

        function closeMenu() {
            toggleBtn.classList.remove('active');
            toggleBtn.setAttribute('aria-expanded', 'false');
            navMenu.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
            body.classList.remove('mobile-nav-open');
        }

        function toggleMenu() {
            const isOpen = navMenu.classList.contains('open');
            if (isOpen) {
                closeMenu();
            } else {
                openMenu();
            }
        }

        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMenu();
        });

        if (overlay) {
            overlay.addEventListener('click', closeMenu);
        }

        // Close when clicking any navigation link inside the menu
        navMenu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                // If on mobile screen, close drawer after click
                if (window.innerWidth <= 860) {
                    closeMenu();
                }
            });
        });

        // Close on Escape key press
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && navMenu.classList.contains('open')) {
                closeMenu();
            }
        });

        // Auto close if window is resized past mobile breakpoint
        window.addEventListener('resize', function() {
            if (window.innerWidth > 860 && navMenu.classList.contains('open')) {
                closeMenu();
            }
        });
    }

    // Interactive swipe indicator for wide data tables on touch screens
    function initResponsiveTables() {
        const tableWraps = document.querySelectorAll('.table-wrap');
        tableWraps.forEach(function(wrap) {
            const table = wrap.querySelector('table');
            if (!table) return;

            function checkOverflow() {
                if (table.scrollWidth > wrap.clientWidth + 5) {
                    wrap.classList.add('has-horizontal-scroll');
                } else {
                    wrap.classList.remove('has-horizontal-scroll');
                }
            }

            checkOverflow();
            window.addEventListener('resize', checkOverflow);
            wrap.addEventListener('scroll', function() {
                if (wrap.scrollLeft > 20) {
                    wrap.classList.add('scrolled-left');
                } else {
                    wrap.classList.remove('scrolled-left');
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initMobileNav();
            initResponsiveTables();
        });
    } else {
        initMobileNav();
        initResponsiveTables();
    }
})();
