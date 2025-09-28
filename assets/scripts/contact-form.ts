(function () {
  // Handle query params for success/error alerts
  const params = new URLSearchParams(window.location.search);
  const successEl = document.getElementById('contact-success');
  const errorEl = document.getElementById('contact-error');

  function showAlert(el: HTMLElement | null, message: string, cls: string): void {
    if (!el) return;
    el.classList.remove('visually-hidden');
    el.setAttribute('aria-hidden', 'false');
    el.classList.add('alert', cls, 'mb-3');
    el.setAttribute('role', 'alert');
    el.innerHTML = message;
  }

  if (params.has('sent')) {
    showAlert(successEl, 'Vielen Dank! Die Nachricht wurde erfolgreich versendet.', 'alert-success');
  } else if (params.has('error')) {
    const e = params.get('error');
    let msg = 'Es ist ein Fehler aufgetreten. Ein erneuter versuch lohnt sich.';
    if (e === 'invalid') msg = 'Bitte das Formular mit den erforderlichen Felder korrekt ausfüllen.';
    else if (e === 'email') msg = 'Bitte eine gültige E‑Mail‑Adresse angeben.';
    else if (e === 'short') msg = 'Die Nachricht ist zu kurz. Bitte eine Nachricht formulieren, damit ich weiß was das Anliegen ist.';
    else if (e === 'rate') msg = 'Bitte einen Moment warten, bevor das Formular erneut abgesendet wird.';
    else if (e === 'mail') msg = 'Der Versand der E‑Mail ist fehlgeschlagen. Ein erneuter versuch lohnt sich.';
    showAlert(errorEl, msg, 'alert-danger');
  }

  // Clean query params but keep anchor for scrolling
  if (params.has('sent') || params.has('error')) {
    const hash = window.location.hash || (params.has('sent') ? '#contact-success' : '#contact-error');
    window.history.replaceState({}, '', window.location.pathname + hash);
  }

  // Client-side validation feedback (support multiple forms with the class)
  const forms = Array.from(document.querySelectorAll<HTMLFormElement>('form.needs-validation'));

  forms.forEach((form) => {
    form.addEventListener('submit', (ev) => {
      if (!form.checkValidity()) {
        ev.preventDefault();
        ev.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
