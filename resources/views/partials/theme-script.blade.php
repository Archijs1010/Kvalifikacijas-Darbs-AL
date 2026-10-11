<script>
    (function () {
        var STORAGE_KEY = 'csfloat-theme';
        var THEMES = ['light', 'dark', 'neon', 'legacy', 'green'];
        var root = document.documentElement;

        function current() {
            var theme = root.getAttribute('data-theme');

            return THEMES.indexOf(theme) !== -1 ? theme : 'dark';
        }

        function apply(theme, persist) {
            if (THEMES.indexOf(theme) === -1) {
                theme = 'dark';
            }

            root.setAttribute('data-theme', theme);

            if (persist) {
                try {
                    window.localStorage.setItem(STORAGE_KEY, theme);
                } catch (error) {
                }
            }

            var select = document.getElementById('theme-select');

            if (select) {
                select.value = theme;
            }

            document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: theme } }));
        }

        function color(name, alpha) {
            var raw = getComputedStyle(root).getPropertyValue(name).trim();
            var parts = raw.split(/[\s,]+/).filter(Boolean).map(Number);

            if (parts.length < 3 || parts.some(isNaN)) {
                return 'rgba(56, 189, 248, ' + (alpha === undefined ? 1 : alpha) + ')';
            }

            return 'rgba(' + parts[0] + ', ' + parts[1] + ', ' + parts[2] + ', '
                + (alpha === undefined ? 1 : alpha) + ')';
        }

        window.CSFloatTheme = {
            themes: THEMES,
            current: current,
            apply: apply,
            color: color,
        };

        var select = document.getElementById('theme-select');

        if (select) {
            select.value = current();
            select.addEventListener('change', function () {
                apply(select.value, true);
            });
        }
    })();
</script>
