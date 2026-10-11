<script>
    (function () {
        var themes = ['light', 'dark', 'neon', 'legacy', 'green'];
        var stored = null;

        try {
            stored = window.localStorage.getItem('csfloat-theme');
        } catch (error) {
            stored = null;
        }

        var theme = themes.indexOf(stored) !== -1 ? stored : 'dark';

        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>

<style>
    :root,
    [data-theme="dark"] {
        color-scheme: dark;
        --surface: 9 9 11;
        --surface-raised: 24 24 27;
        --border: 39 39 42;
        --border-strong: 63 63 70;
        --text: 244 244 245;
        --text-soft: 212 212 216;
        --text-muted: 161 161 170;
        --text-subtle: 113 113 122;
        --text-faint: 82 82 91;
        --accent-soft: 125 211 252;
        --accent-muted: 56 189 248;
        --accent: 14 165 233;
        --accent-strong: 2 132 199;
        --success-soft: 110 231 183;
        --success: 52 211 153;
        --success-strong: 16 185 129;
        --danger-soft: 252 165 165;
        --danger: 248 113 113;
        --danger-strong: 239 68 68;
        --warning-soft: 252 211 77;
        --warning: 251 191 36;
        --warning-strong: 245 158 11;
        --on-accent: 255 255 255;
    }

    [data-theme="light"] {
        color-scheme: light;
        --surface: 244 244 245;
        --surface-raised: 255 255 255;
        --border: 228 228 231;
        --border-strong: 212 212 216;
        --text: 24 24 27;
        --text-soft: 39 39 42;
        --text-muted: 63 63 70;
        --text-subtle: 82 82 91;
        --text-faint: 113 113 122;
        --accent-soft: 2 132 199;
        --accent-muted: 2 132 199;
        --accent: 2 132 199;
        --accent-strong: 3 105 161;
        --success-soft: 4 120 87;
        --success: 4 120 87;
        --success-strong: 5 150 105;
        --danger-soft: 185 28 28;
        --danger: 185 28 28;
        --danger-strong: 220 38 38;
        --warning-soft: 146 64 14;
        --warning: 180 83 9;
        --warning-strong: 217 119 6;
        --on-accent: 255 255 255;
    }

    [data-theme="neon"] {
        color-scheme: dark;
        --surface: 2 2 10;
        --surface-raised: 8 8 26;
        --border: 30 30 62;
        --border-strong: 62 62 116;
        --text: 224 255 255;
        --text-soft: 168 240 255;
        --text-muted: 118 205 235;
        --text-subtle: 92 158 198;
        --text-faint: 70 116 152;
        --accent-soft: 130 255 255;
        --accent-muted: 0 255 255;
        --accent: 0 230 230;
        --accent-strong: 0 200 220;
        --success-soft: 120 255 190;
        --success: 0 255 140;
        --success-strong: 0 220 120;
        --danger-soft: 255 130 170;
        --danger: 255 60 120;
        --danger-strong: 230 30 90;
        --warning-soft: 255 240 130;
        --warning: 255 220 0;
        --warning-strong: 230 190 0;
        --on-accent: 2 2 10;
    }

    [data-theme="legacy"] {
        color-scheme: dark;
        --surface: 26 20 12;
        --surface-raised: 38 30 20;
        --border: 60 48 32;
        --border-strong: 92 74 48;
        --text: 240 224 190;
        --text-soft: 214 194 158;
        --text-muted: 182 158 120;
        --text-subtle: 152 128 96;
        --text-faint: 122 100 72;
        --accent-soft: 245 205 130;
        --accent-muted: 230 180 90;
        --accent: 210 160 60;
        --accent-strong: 186 134 40;
        --success-soft: 170 205 130;
        --success: 140 190 110;
        --success-strong: 116 166 88;
        --danger-soft: 235 150 120;
        --danger: 220 120 90;
        --danger-strong: 196 96 66;
        --warning-soft: 245 205 130;
        --warning: 235 190 80;
        --warning-strong: 210 164 52;
        --on-accent: 26 20 12;
    }

    [data-theme="green"] {
        color-scheme: dark;
        --surface: 0 10 2;
        --surface-raised: 3 22 8;
        --border: 12 52 22;
        --border-strong: 22 82 38;
        --text: 150 255 172;
        --text-soft: 122 232 150;
        --text-muted: 94 202 122;
        --text-subtle: 72 162 96;
        --text-faint: 52 122 76;
        --accent-soft: 150 255 178;
        --accent-muted: 84 240 124;
        --accent: 62 220 104;
        --accent-strong: 40 184 82;
        --success-soft: 150 255 178;
        --success: 84 240 124;
        --success-strong: 52 210 96;
        --danger-soft: 255 140 140;
        --danger: 240 90 90;
        --danger-strong: 214 64 64;
        --warning-soft: 240 224 130;
        --warning: 230 210 90;
        --warning-strong: 204 184 60;
        --on-accent: 0 12 2;
    }
</style>

<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    zinc: {
                        100: 'rgb(var(--text) / <alpha-value>)',
                        300: 'rgb(var(--text-soft) / <alpha-value>)',
                        400: 'rgb(var(--text-muted) / <alpha-value>)',
                        500: 'rgb(var(--text-subtle) / <alpha-value>)',
                        600: 'rgb(var(--text-faint) / <alpha-value>)',
                        700: 'rgb(var(--border-strong) / <alpha-value>)',
                        800: 'rgb(var(--border) / <alpha-value>)',
                        900: 'rgb(var(--surface-raised) / <alpha-value>)',
                        950: 'rgb(var(--surface) / <alpha-value>)',
                    },
                    sky: {
                        300: 'rgb(var(--accent-soft) / <alpha-value>)',
                        400: 'rgb(var(--accent-muted) / <alpha-value>)',
                        500: 'rgb(var(--accent) / <alpha-value>)',
                        600: 'rgb(var(--accent-strong) / <alpha-value>)',
                    },
                    emerald: {
                        300: 'rgb(var(--success-soft) / <alpha-value>)',
                        400: 'rgb(var(--success) / <alpha-value>)',
                        500: 'rgb(var(--success-strong) / <alpha-value>)',
                    },
                    red: {
                        300: 'rgb(var(--danger-soft) / <alpha-value>)',
                        400: 'rgb(var(--danger) / <alpha-value>)',
                        500: 'rgb(var(--danger-strong) / <alpha-value>)',
                    },
                    amber: {
                        300: 'rgb(var(--warning-soft) / <alpha-value>)',
                        400: 'rgb(var(--warning) / <alpha-value>)',
                        500: 'rgb(var(--warning-strong) / <alpha-value>)',
                    },
                    white: 'rgb(var(--on-accent) / <alpha-value>)',
                },
            },
        },
    };
</script>
