{{-- White backdrop: the form is built for landing pages, also when the panel is in dark mode. --}}
<div
    x-data="{
        height: 600,
        resize(event) {
            const fromPreview = event.origin === window.location.origin
                && event.source === this.$refs.frame.contentWindow;

            if (fromPreview && event.data?.type === 'leadflow:resize' && event.data.height > 0) {
                this.height = Math.ceil(event.data.height);
            }
        },
    }"
    x-on:message.window="resize($event)"
    style="padding: 1rem; background: #fff; border-radius: 0.5rem;"
>
    <iframe
        x-ref="frame"
        src="{{ route('embed.form', ['offer' => $getRecord(), 'preview' => 1]) }}"
        title="Anteprima del modulo"
        scrolling="no"
        x-bind:style="{ height: height + 'px' }"
        style="display: block; width: 100%; border: 0;"
    ></iframe>
</div>
