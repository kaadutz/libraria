// Initialize Tailwind Config
tailwind.config = {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: "#3a5020",
        "chocolate": "#633d0c",
        "tan": "#b08144",
        "sage": "#d1d6a7",
        "cream": "#fefbe9",
        "background-light": "#fefbe9",
        "background-dark": "#1a1c18",
      }
    }
  }
};

// CSS Injection for Dark Mode Overrides
(function injectStyles() {
    const style = document.createElement('style');
    style.textContent = `
        /* Dark Mode Variables Mapping - Aligned with Landing Page */
        :root.dark {
            --deep-forest: #d1d6a7;      /* Sage (Used for Headlines/Icons in dark mode) */
            --chocolate-brown: #b08144;  /* Tan (Accents) */
            --warm-tan: #8a5a1b;         /* Lighter Chocolate */
            --light-sage: #2f421b;       /* Dark Green Backgrounds */
            --cream-bg: #1a1c18;         /* Landing Page Dark Background */
            --text-dark: #e6e2dd;        /* Sand/Off-white Text */
            --text-muted: #a8a29e;       /* Stone 400 */
            --border-color: #292524;     /* Stone 800 */
        }

        /* Base Body Override */
        body.dark {
            background-color: var(--cream-bg) !important;
            color: var(--text-dark) !important;
        }

        /* Utility Class Overrides to enforce specific colors */
        .dark .bg-white { background-color: #1c1917 !important; } /* Stone 900 - Card Background */
        .dark .bg-stone-50 { background-color: #0c0a09 !important; } /* Stone 950 */
        .dark .bg-gray-50 { background-color: #0c0a09 !important; }
        .dark .bg-cream { background-color: #1a1c18 !important; }

        /* Text Color Overrides */
        .dark .text-stone-500 { color: #a8a29e !important; } /* Stone 400 */
        .dark .text-gray-500 { color: #9ca3af !important; } /* Gray 400 */
        .dark .text-stone-600 { color: #d6d3d1 !important; } /* Stone 300 */
        .dark .text-gray-600 { color: #d1d5db !important; } /* Gray 300 */
        .dark .text-stone-800 { color: #e6e2dd !important; } /* Main Text */
        .dark .text-gray-800 { color: #f3f4f6 !important; }

        /* Border Overrides */
        .dark .border-stone-200 { border-color: #292524 !important; } /* Stone 800 */
        .dark .border-gray-200 { border-color: #374151 !important; } /* Gray 700 */
        .dark .border-gray-100 { border-color: #374151 !important; }

        /* Form Elements */
        .dark input, .dark select, .dark textarea {
            background-color: #0c0a09 !important; /* Stone 950 */
            color: #e6e2dd !important;
            border-color: #292524 !important;
        }

        /* Specific Landing Page alignments */
        .dark .text-primary { color: #d1d6a7 !important; } /* Map primary text to Sage in dark mode */

        /* Backdrop blurs adjustments */
        .dark .bg-white\\/90 { background-color: rgba(28, 25, 23, 0.9) !important; }
        .dark .bg-white\\/50 { background-color: rgba(28, 25, 23, 0.5) !important; }

        /* Shadow adjustments */
        .dark .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5), 0 4px 6px -2px rgba(0, 0, 0, 0.3) !important; }
    `;
    document.head.appendChild(style);
})();

// Theme Logic
function toggleDarkMode() {
    const html = document.documentElement;

    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.theme = 'light';
    } else {
        html.classList.add('dark');
        localStorage.theme = 'dark';
    }
    updateIcons();
}

function updateIcons() {
    const isDark = document.documentElement.classList.contains('dark');
    const iconText = isDark ? 'light_mode' : 'dark_mode';

    const icons = document.querySelectorAll('#dark-mode-icon');
    icons.forEach(icon => {
        icon.textContent = iconText;
    });
}

// Initialization
(function initTheme() {
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateIcons);
    } else {
        updateIcons();
    }
})();
