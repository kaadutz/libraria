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
        /* Dark Mode Variables Mapping */
        :root.dark {
            --deep-forest: #DCE3AC;      /* Light Sage (Text/Icons) */
            --chocolate-brown: #B18143;  /* Warm Tan (Accents) */
            --warm-tan: #663F05;         /* Chocolate (Darker accents) */
            --light-sage: #2f421b;       /* Dark Green (Backgrounds) */
            --cream-bg: #1a1c18;         /* Dark Charcoal (Main Background) */
            --text-dark: #e6e2dd;        /* Light Sand (Main Text) */
            --text-muted: #a8a29e;       /* Light Gray (Muted Text) */
            --border-color: #44403c;     /* Dark Stone (Borders) */
        }

        /* Base Body Override */
        body.dark {
            background-color: var(--cream-bg) !important;
            color: var(--text-dark) !important;
        }

        /* Utility Class Overrides */
        .dark .bg-white { background-color: #1c1917 !important; } /* stone-900 */
        .dark .bg-stone-50 { background-color: #1f2937 !important; } /* gray-800 */
        .dark .bg-gray-50 { background-color: #1f2937 !important; }
        .dark .bg-cream { background-color: #1a1c18 !important; }

        .dark .text-stone-500 { color: #a8a29e !important; } /* stone-400 */
        .dark .text-gray-500 { color: #9ca3af !important; } /* gray-400 */
        .dark .text-stone-600 { color: #d6d3d1 !important; } /* stone-300 */
        .dark .text-gray-600 { color: #d1d5db !important; } /* gray-300 */
        .dark .text-stone-800 { color: #e6e2dd !important; }
        .dark .text-gray-800 { color: #f3f4f6 !important; }

        .dark .border-stone-200 { border-color: #44403c !important; } /* stone-700 */
        .dark .border-gray-200 { border-color: #374151 !important; } /* gray-700 */
        .dark .border-gray-100 { border-color: #374151 !important; }

        /* Component Fixes */
        .dark .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5), 0 4px 6px -2px rgba(0, 0, 0, 0.3) !important; }
        .dark input, .dark select, .dark textarea {
            background-color: #292524 !important; /* stone-800 */
            color: #e7e5e4 !important; /* stone-200 */
            border-color: #44403c !important;
        }

        /* Ensure specific backgrounds don't clash */
        .dark .bg-white\\/90 { background-color: rgba(28, 25, 23, 0.9) !important; }
        .dark .bg-white\\/50 { background-color: rgba(28, 25, 23, 0.5) !important; }
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
    // If dark, show 'light_mode' (sun) to switch to light.
    // If light, show 'dark_mode' (moon) to switch to dark.
    const iconText = isDark ? 'light_mode' : 'dark_mode';

    const icons = document.querySelectorAll('#dark-mode-icon');
    icons.forEach(icon => {
        icon.textContent = iconText;
    });
}

// Initialization
(function initTheme() {
    // Check saved theme or system preference
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }

    // Update icons when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateIcons);
    } else {
        updateIcons();
    }
})();
