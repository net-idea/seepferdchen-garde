(function () {
  // Handle query params for success/error alerts
  const params = new URLSearchParams(window.location.search);
  const hash = window.location.hash;
  const successEl = document.getElementById('contact-success');
  const errorEl = document.getElementById('contact-error');

  function showAlert(el: HTMLElement | null, message: string, cls: string): void {
    if (!el) return;
    el.classList.remove('visually-hidden');
    el.setAttribute('aria-hidden', 'false');
    el.classList.add('alert', cls, 'mb-3');
    el.setAttribute('role', 'alert');
    el.innerHTML = message;
    // Improve visibility/accessibility
    el.setAttribute('tabindex', '-1');
    setTimeout(() => {
      el.scrollIntoView({behavior: 'smooth', block: 'start'});
      try {
        (el as HTMLElement).focus({preventScroll: true});
      } catch { /* noop */
      }
    }, 0);
  }

  const hasSent = params.has('sent') || hash === '#contact-success';
  const hasError = params.has('error') || hash === '#contact-error';

  if (hasSent) {
    showAlert(
      successEl,
      'Vielen Dank! Ihre Nachricht wurde erfolgreich versendet. Ich melde mich zeitnah bei Ihnen.',
      'alert-success'
    );
  } else if (hasError) {
    const e = params.get('error');
    let msg = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es in Kürze erneut.';
    if (e === 'invalid') msg = 'Bitte füllen Sie die erforderlichen Felder korrekt aus.';
    else if (e === 'email') msg = 'Bitte geben Sie eine gültige E‑Mail‑Adresse an.';
    else if (e === 'short') msg = 'Die Nachricht ist zu kurz. Bitte eine Nachricht formulieren, damit ich weiß was das Anliegen ist.';
    else if (e === 'rate') msg = 'Bitte einen Moment warten, bevor das Formular erneut abgesendet wird.';
    else if (e === 'mail') msg = 'Der Versand der E‑Mail ist fehlgeschlagen. Ein erneuter Versuch lohnt sich.';
    showAlert(errorEl, msg, 'alert-danger');
  }

  // Clean query params but keep anchor for scrolling
  if (params.has('sent') || params.has('error')) {
    const newHash = hash || (params.has('sent') ? '#contact-success' : '#contact-error');
    window.history.replaceState({}, '', window.location.pathname + newHash);
  }

  // Client-side validation feedback (support multiple forms with the class)
  const forms = Array.from(document.querySelectorAll<HTMLFormElement>('form.needs-validation'));

  forms.forEach((form) => {
    form.addEventListener(
      'submit',
      (ev) => {
        if (!form.checkValidity()) {
          ev.preventDefault();
          ev.stopPropagation();
        }
        form.classList.add('was-validated');
      },
      false
    );
  });
})();
