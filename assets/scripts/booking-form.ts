import { enhanceAjaxForm, handleRedirectFlags } from './ajax-form';

(function () {
  handleRedirectFlags('booking', {
    __success: 'Vielen Dank! Ihre Anfrage ist eingegangen. Bitte bestätigen Sie sie über den Link in der E‑Mail.',
    rate: 'Bitte einen Moment warten, bevor das Formular erneut abgesendet wird.',
    mail: 'Ihre Anfrage wurde gespeichert, aber die Bestätigungs‑E‑Mail konnte nicht versendet werden. Ich melde mich bei Ihnen.',
    db: 'Es ist ein technischer Fehler aufgetreten und Ihre Anfrage konnte nicht gespeichert werden. Bitte versuchen Sie es später erneut.',
  });

  const form = document.querySelector<HTMLFormElement>('form[name="form_booking"]');
  if (form) enhanceAjaxForm(form);

  // Print button lives inside the (possibly AJAX-injected) summary -> event delegation
  document.addEventListener('click', (ev) => {
    const target = ev.target as HTMLElement | null;
    if (target?.closest('#booking-print')) {
      ev.preventDefault();
      window.print();
    }
  });
})();
