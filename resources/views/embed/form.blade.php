@extends('embed.layout')

@section('content')
    <div class="card">
        <div id="leadflow-form-wrapper">
            <h1>Richiedi di essere ricontattato</h1>
            <p class="intro">Questa è una richiesta di ricontatto, non un acquisto: nessun pagamento è richiesto.</p>

            <p class="notice notice--error" id="leadflow-error" role="alert" hidden></p>

            <form id="leadflow-form" action="{{ $submitUrl }}" method="post" novalidate>
                <div class="field">
                    <label for="first_name">Nome</label>
                    <input id="first_name" name="first_name" type="text" maxlength="100" autocomplete="given-name" required>
                    <p class="error" data-error-for="first_name" hidden></p>
                </div>

                <div class="field">
                    <label for="last_name">Cognome</label>
                    <input id="last_name" name="last_name" type="text" maxlength="100" autocomplete="family-name" required>
                    <p class="error" data-error-for="last_name" hidden></p>
                </div>

                <div class="field">
                    <label for="phone">Telefono</label>
                    <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" required>
                    <p class="error" data-error-for="phone" hidden></p>
                </div>

                <div class="field">
                    <label for="address">Indirizzo <span class="optional">(facoltativo)</span></label>
                    <textarea id="address" name="address" maxlength="1000" autocomplete="street-address"></textarea>
                    <p class="error" data-error-for="address" hidden></p>
                </div>

                <div class="trap" aria-hidden="true">
                    <label for="{{ \App\Http\Requests\SubmitOrderRequest::HONEYPOT }}">Lascia vuoto questo campo</label>
                    <input id="{{ \App\Http\Requests\SubmitOrderRequest::HONEYPOT }}" name="{{ \App\Http\Requests\SubmitOrderRequest::HONEYPOT }}" type="text" tabindex="-1" autocomplete="off">
                </div>

                <input type="hidden" name="source_url" value="{{ $sourceUrl }}">

                <button id="leadflow-submit" type="submit">Invia richiesta</button>

                <p class="privacy">
                    @if ($privacyUrl)
                        Consulta l'<a href="{{ $privacyUrl }}" target="_blank" rel="noopener noreferrer">informativa privacy</a>.
                    @else
                        Informativa privacy in fase di configurazione.
                    @endif
                </p>
            </form>
        </div>

        <p class="notice notice--success" id="leadflow-success" role="status" hidden></p>
    </div>
@endsection

@push('scripts')
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        (function () {
            var form = document.getElementById('leadflow-form');
            var wrapper = document.getElementById('leadflow-form-wrapper');
            var submit = document.getElementById('leadflow-submit');
            var generalError = document.getElementById('leadflow-error');
            var success = document.getElementById('leadflow-success');
            var GENERIC_ERROR = 'Si è verificato un errore. Riprova tra qualche istante.';
            var IS_PREVIEW = @json($isPreview);
            var PREVIEW_NOTICE_MS = 4000;

            function clearErrors() {
                generalError.hidden = true;
                form.querySelectorAll('[data-error-for]').forEach(function (element) {
                    element.hidden = true;
                    element.textContent = '';
                });
                form.querySelectorAll('.invalid').forEach(function (element) {
                    element.classList.remove('invalid');
                });
            }

            function showGeneralError(message) {
                generalError.textContent = message;
                generalError.hidden = false;
            }

            function showFieldErrors(errors) {
                Object.keys(errors).forEach(function (name) {
                    var element = form.querySelector('[data-error-for="' + name + '"]');
                    var input = form.elements[name];

                    if (element) {
                        element.textContent = errors[name][0];
                        element.hidden = false;
                    }

                    if (input) {
                        input.classList.add('invalid');
                    }
                });
            }

            function showSuccess(message) {
                success.textContent = message;
                success.hidden = false;
                wrapper.hidden = true;
            }

            // In the panel preview nothing is sent: only the confirmation is shown.
            function showPreviewNotice() {
                showSuccess('Anteprima: la richiesta non è stata inviata e non è stato creato alcun ordine.');
                window.leadflowReportHeight();

                setTimeout(function () {
                    success.hidden = true;
                    wrapper.hidden = false;
                    window.leadflowReportHeight();
                }, PREVIEW_NOTICE_MS);
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                clearErrors();

                if (IS_PREVIEW) {
                    showPreviewNotice();

                    return;
                }

                submit.disabled = true;

                fetch(form.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(form),
                    credentials: 'omit'
                }).then(function (response) {
                    return response.json().catch(function () {
                        return {};
                    }).then(function (body) {
                        if (response.status === 201) {
                            showSuccess(body.message);
                        } else if (response.status === 422 && body.errors) {
                            showFieldErrors(body.errors);
                        } else {
                            showGeneralError(body.message || GENERIC_ERROR);
                        }
                    });
                }).catch(function () {
                    showGeneralError(GENERIC_ERROR);
                }).finally(function () {
                    submit.disabled = false;
                    window.leadflowReportHeight();
                });
            });
        })();
    </script>
@endpush
