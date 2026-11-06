<script>
    (function () {
        // Changes arriving close together become a single refresh of the page.
        const REFRESH_DELAY_MS = 500;
        let pendingRefresh = null;

        function scheduleRefresh() {
            if (pendingRefresh) {
                return;
            }

            pendingRefresh = setTimeout(function () {
                pendingRefresh = null;
                window.Livewire.dispatch('orders-changed');
                window.Livewire.dispatch('refresh-sidebar');
            }, REFRESH_DELAY_MS);
        }

        function subscribe() {
            window.Echo.private('orders').listen('.order.changed', scheduleRefresh);
        }

        if (window.Echo) {
            subscribe();
        } else {
            window.addEventListener('EchoLoaded', subscribe, { once: true });
        }
    })();
</script>
