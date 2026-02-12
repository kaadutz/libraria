// Dark Mode Management
(function() {
    // Check local storage or system preference on load
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
})();

function toggleDarkMode() {
    const html = document.documentElement;
    const icons = document.querySelectorAll('#dark-mode-icon');

    html.classList.add('transition-colors', 'duration-500');

    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.theme = 'light';
        icons.forEach(icon => icon.textContent = 'dark_mode');
    } else {
        html.classList.add('dark');
        localStorage.theme = 'dark';
        icons.forEach(icon => icon.textContent = 'light_mode');
    }
}

// Update icon on load
window.addEventListener('DOMContentLoaded', () => {
    const icons = document.querySelectorAll('#dark-mode-icon');
    if (document.documentElement.classList.contains('dark')) {
        icons.forEach(icon => icon.textContent = 'light_mode');
    } else {
        icons.forEach(icon => icon.textContent = 'dark_mode');
    }
});