// theme-manager.js

// 1. Initial Check (Run immediately)
if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
} else {
    document.documentElement.classList.remove('dark');
}

// 2. Toggle Function
function toggleDarkMode() {
    const html = document.documentElement;
    const icon = document.getElementById('dark-mode-icon');

    html.classList.add('transition-colors', 'duration-500'); // Add transition only when toggling

    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.theme = 'light';
        if(icon) icon.innerText = 'dark_mode'; // Material Icon text
    } else {
        html.classList.add('dark');
        localStorage.theme = 'dark';
        if(icon) icon.innerText = 'light_mode'; // Material Icon text
    }
}

// 3. Update Icon on Load (in case the button exists on page load)
document.addEventListener('DOMContentLoaded', () => {
    const icon = document.getElementById('dark-mode-icon');
    if (icon) {
        if (document.documentElement.classList.contains('dark')) {
            icon.innerText = 'light_mode';
        } else {
            icon.innerText = 'dark_mode';
        }
    }
});
