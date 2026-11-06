(function () {
    'use strict';

    var formUrl = @js($formUrl);
    var formOrigin = @js($formOrigin);
    var script = document.currentScript;

    function hostPageUrl() {
        try {
            return window.location.origin + window.location.pathname;
        } catch (error) {
            return '';
        }
    }

    function mount(iframe) {
        if (script && script.parentNode) {
            script.parentNode.insertBefore(iframe, script.nextSibling);
        } else {
            document.body.appendChild(iframe);
        }
    }

    var iframe = document.createElement('iframe');
    iframe.src = formUrl + '?source=' + encodeURIComponent(hostPageUrl());
    iframe.title = 'Modulo di richiesta di ricontatto';
    iframe.setAttribute('scrolling', 'no');
    iframe.style.cssText = 'display:block;width:100%;max-width:100%;height:520px;border:0;overflow:hidden;background:transparent;';

    // Only the height is accepted, and only from this iframe on the LeadFlow origin.
    window.addEventListener('message', function (event) {
        if (event.origin !== formOrigin || event.source !== iframe.contentWindow) {
            return;
        }

        var data = event.data;

        if (data && data.type === 'leadflow:resize' && typeof data.height === 'number' && isFinite(data.height)) {
            iframe.style.height = Math.min(Math.max(Math.ceil(data.height), 120), 1600) + 'px';
        }
    });

    mount(iframe);
})();
