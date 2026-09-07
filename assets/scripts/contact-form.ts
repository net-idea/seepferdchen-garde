import { enhanceAjaxForm, handleRedirectFlags } from './ajax-form';

(function () {
  handleRedirectFlags('contact', {
    __success: 'Vielen Dank! Ihre Nachricht wurde erfolgreich versendet. Ich melde mich zeitnah bei Ihnen.',
    rate: 'Bitte einen Moment warten, bevor das Formular erneut abgesendet wird.',
    mail: 'Der Versand der E‑Mail ist fehlgeschlagen. Ein erneuter Versuch lohnt sich.',
    db: 'Es ist ein technischer Fehler aufgetreten. Bitte versuchen Sie es in wenigen Minuten erneut.',
  });

  const form = document.querySelector<HTMLFormElement>('form[name="form_contact"]');
  if (form) enhanceAjaxForm(form);
})();
