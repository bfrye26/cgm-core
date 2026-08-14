import type { Config } from 'tailwindcss';

const config: Config = {
  darkMode: ['class'],
  content: ['./src/Admin/**/*.{ts,tsx}'],
  important: '.cgm-core-root',
  theme: {
    extend: {
      fontFamily: {
        display: ['Space Grotesk', 'IBM Plex Sans', 'sans-serif'],
        sans: ['IBM Plex Sans', 'system-ui', 'sans-serif'],
        mono: ['IBM Plex Mono', 'ui-monospace', 'monospace'],
      },
      colors: {
        paper: 'hsl(var(--paper))',
        surface: 'hsl(var(--surface))',
        'surface-2': 'hsl(var(--surface-2))',
        ink: 'hsl(var(--ink))',
        'ink-soft': 'hsl(var(--ink-soft))',
        'ink-faint': 'hsl(var(--ink-faint))',
        line: 'hsl(var(--line))',
        'line-strong': 'hsl(var(--line-strong))',
        indigo: {
          DEFAULT: 'hsl(var(--indigo))',
          bright: 'hsl(var(--indigo-bright))',
          soft: 'hsl(var(--indigo-soft))',
          ink: 'hsl(var(--indigo-ink))',
        },
        amber: {
          DEFAULT: 'hsl(var(--amber))',
          bright: 'hsl(var(--amber-bright))',
          soft: 'hsl(var(--amber-soft))',
          ink: 'hsl(var(--amber-ink))',
        },
        pine: { DEFAULT: 'hsl(var(--pine))', soft: 'hsl(var(--pine-soft))' },
        gold: { DEFAULT: 'hsl(var(--gold))', soft: 'hsl(var(--gold-soft))' },
        rust: { DEFAULT: 'hsl(var(--rust))', soft: 'hsl(var(--rust-soft))' },
        border: 'hsl(var(--border))',
        input: 'hsl(var(--input))',
        ring: 'hsl(var(--ring))',
        background: 'hsl(var(--background))',
        foreground: 'hsl(var(--foreground))',
        primary: { DEFAULT: 'hsl(var(--primary))', foreground: 'hsl(var(--primary-foreground))' },
        secondary: { DEFAULT: 'hsl(var(--secondary))', foreground: 'hsl(var(--secondary-foreground))' },
        muted: { DEFAULT: 'hsl(var(--muted))', foreground: 'hsl(var(--muted-foreground))' },
        accent: { DEFAULT: 'hsl(var(--accent))', foreground: 'hsl(var(--accent-foreground))' },
        destructive: { DEFAULT: 'hsl(var(--destructive))', foreground: 'hsl(var(--destructive-foreground))' },
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 4px)',
        sm: 'calc(var(--radius) - 6px)',
      },
      boxShadow: {
        card: '0 1px 2px hsl(var(--ink) / 0.04), 0 4px 16px -6px hsl(var(--ink) / 0.08)',
        lift: '0 2px 4px hsl(var(--ink) / 0.06), 0 12px 28px -8px hsl(var(--ink) / 0.16)',
        focus: '0 0 0 3px hsl(var(--indigo-bright) / 0.22)',
      },
    },
  },
  plugins: [require('tailwindcss-animate')],
};

export default config;
