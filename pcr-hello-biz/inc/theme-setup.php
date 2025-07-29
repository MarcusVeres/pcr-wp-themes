<?php
/**
 * Core theme setup - enqueue styles
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue parent and child theme styles
 */
function pcr_enqueue_child_styles() {
    // Parent theme CSS
    wp_enqueue_style(
        'hello-biz-parent',
        get_template_directory_uri() . '/style.css',
        array(),
        wp_get_theme(get_template())->get('Version') // Fixed: get parent theme version
    );
    
    // Child theme CSS
    wp_enqueue_style(
        'pcr-hello-biz-child',
        get_stylesheet_directory_uri() . '/style.css', // Fixed: was styles.css
        array('hello-biz-parent'),
        wp_get_theme()->get('Version') // Child theme version
    );
}
add_action('wp_enqueue_scripts', 'pcr_enqueue_child_styles');

/**
 * Add scroll offset CSS variables and styles
 */
function pcr_add_scroll_offset_styles() {
    // Only add if scroll offset is enabled
    if (!function_exists('pcr_is_scroll_offset_enabled') || !pcr_is_scroll_offset_enabled()) {
        return;
    }

    $desktop_offset = function_exists('pcr_get_scroll_offset_desktop') ? pcr_get_scroll_offset_desktop() : 100;
    $mobile_offset = function_exists('pcr_get_scroll_offset_mobile') ? pcr_get_scroll_offset_mobile() : 80;
    $breakpoint = function_exists('pcr_get_scroll_offset_breakpoint') ? pcr_get_scroll_offset_breakpoint() : 768;

    ?>
    <style id="pcr-scroll-offset-styles">
        :root {
            --pcr-scroll-offset-desktop: <?php echo $desktop_offset; ?>px;
            --pcr-scroll-offset-mobile: <?php echo $mobile_offset; ?>px;
            --pcr-scroll-offset-breakpoint: <?php echo $breakpoint; ?>px;
        }

        /* Modern browsers: use scroll-padding-top */
        html {
            scroll-padding-top: var(--pcr-scroll-offset-desktop);
        }

        @media (max-width: <?php echo $breakpoint; ?>px) {
            html {
                scroll-padding-top: var(--pcr-scroll-offset-mobile);
            }
        }

        /* Smooth scrolling for better UX */
        html {
            scroll-behavior: smooth;
        }

        /* Fallback for older browsers - add margin to targeted elements */
        .pcr-anchor-offset::before {
            content: '';
            display: block;
            height: var(--pcr-scroll-offset-desktop);
            margin-top: calc(var(--pcr-scroll-offset-desktop) * -1);
            visibility: hidden;
            pointer-events: none;
        }

        @media (max-width: <?php echo $breakpoint; ?>px) {
            .pcr-anchor-offset::before {
                height: var(--pcr-scroll-offset-mobile);
                margin-top: calc(var(--pcr-scroll-offset-mobile) * -1);
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'pcr_add_scroll_offset_styles');

/**
 * Add scroll offset JavaScript for enhanced functionality
 */
function pcr_add_scroll_offset_script() {
    // Only add if scroll offset is enabled
    if (!function_exists('pcr_is_scroll_offset_enabled') || !pcr_is_scroll_offset_enabled()) {
        return;
    }

    $desktop_offset = function_exists('pcr_get_scroll_offset_desktop') ? pcr_get_scroll_offset_desktop() : 100;
    $mobile_offset = function_exists('pcr_get_scroll_offset_mobile') ? pcr_get_scroll_offset_mobile() : 80;
    $breakpoint = function_exists('pcr_get_scroll_offset_breakpoint') ? pcr_get_scroll_offset_breakpoint() : 768;

    ?>
    <script id="pcr-scroll-offset-script">
    (function() {
        'use strict';
        
        const scrollOffsets = {
            desktop: <?php echo $desktop_offset; ?>,
            mobile: <?php echo $mobile_offset; ?>,
            breakpoint: <?php echo $breakpoint; ?>
        };

        function getCurrentOffset() {
            return window.innerWidth <= scrollOffsets.breakpoint 
                ? scrollOffsets.mobile 
                : scrollOffsets.desktop;
        }

        // Enhanced anchor link handling
        function handleAnchorClick(e) {
            const link = e.target.closest('a[href^="#"]');
            if (!link) return;

            const href = link.getAttribute('href');
            if (href === '#' || href === '#top') return;

            const targetId = href.substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (!targetElement) return;

            e.preventDefault();

            const rect = targetElement.getBoundingClientRect();
            const offsetTop = window.pageYOffset + rect.top - getCurrentOffset();

            // Use smooth scrolling
            window.scrollTo({
                top: offsetTop,
                behavior: 'smooth'
            });

            // Update URL hash
            if (history.pushState) {
                history.pushState(null, null, href);
            } else {
                // Fallback for older browsers
                location.hash = href;
            }
        }

        // Handle hash on page load
        function handleInitialHash() {
            const hash = window.location.hash;
            if (!hash) return;

            // Small delay to ensure page is fully loaded
            setTimeout(function() {
                const targetElement = document.querySelector(hash);
                if (targetElement) {
                    const rect = targetElement.getBoundingClientRect();
                    const offsetTop = window.pageYOffset + rect.top - getCurrentOffset();
                    
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            }, 100);
        }

        // Add click event listener
        document.addEventListener('click', handleAnchorClick);

        // Handle initial hash when page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', handleInitialHash);
        } else {
            handleInitialHash();
        }

        // Handle browser back/forward with hash changes
        window.addEventListener('hashchange', function() {
            const hash = window.location.hash;
            if (hash) {
                const targetElement = document.querySelector(hash);
                if (targetElement) {
                    const rect = targetElement.getBoundingClientRect();
                    const offsetTop = window.pageYOffset + rect.top - getCurrentOffset();
                    
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            }
        });

        // Debug function (remove in production)
        window.pcrScrollOffset = {
            getCurrentOffset: getCurrentOffset,
            scrollOffsets: scrollOffsets
        };

    })();
    </script>
    <?php
}
add_action('wp_footer', 'pcr_add_scroll_offset_script');

/**
 * Add body class when scroll offset is enabled
 */
function pcr_scroll_offset_body_class($classes) {
    if (function_exists('pcr_is_scroll_offset_enabled') && pcr_is_scroll_offset_enabled()) {
        $classes[] = 'pcr-scroll-offset-enabled';
    }
    return $classes;
}
add_filter('body_class', 'pcr_scroll_offset_body_class');