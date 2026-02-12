// Tailwind Configuration
tailwind.config = {
    darkMode: "class",
    theme: {
      extend: {
        colors: {
          primary: "#3a5020",
          "primary-light": "#537330",
          "chocolate": "#633d0c",
          "chocolate-light": "#8a5a1b",
          "tan": "#b08144",
          "sand": "#e6e2dd",
          "sage": "#d1d6a7",
          "sage-dark": "#aeb586",
          "cream": "#fefbe9",
          "background-light": "#fefbe9",
          "background-dark": "#1a1c18",
          // Mapping existing CSS variables to Tailwind colors for consistency
          "deep-forest": "#3E4B1C",
          "chocolate-brown": "#663F05",
          "warm-tan": "#B18143",
          "light-sage": "#DCE3AC",
          "cream-bg": "#FEF9E6",
          "text-dark": "#2D2418",
          "text-muted": "#6B6155",
          "border-color": "#E6E1D3",
        },
        fontFamily: {
          display: ["DM Serif Display", "serif"],
          sans: ["Inter", "sans-serif"],
          logo: ["Cinzel", "serif"],
        },
        boxShadow: {
            'card': '0 20px 40px -5px rgba(58, 80, 32, 0.08)',
            'glow': '0 0 20px rgba(176, 129, 68, 0.4)',
            'paper': '2px 4px 12px rgba(99, 61, 12, 0.08)',
            'book-3d': '5px 5px 15px rgba(0,0,0,0.2), 10px 10px 25px rgba(0,0,0,0.1)',
        },
        animation: {
            'marquee': 'marquee 40s linear infinite',
            'float-slow': 'float 6s ease-in-out infinite',
        },
        keyframes: {
            marquee: {
                '0%': { transform: 'translateX(0%)' },
                '100%': { transform: 'translateX(-100%)' },
            },
            float: {
                '0%, 100%': { transform: 'translateY(0)' },
                '50%': { transform: 'translateY(-15px)' },
            }
        }
      },
    },
  };