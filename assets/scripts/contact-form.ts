import { enhanceAjaxForm, handleRedirectFlags } from './ajax-form';
import { solveChallenge } from './proof-of-work';

// The server rejects (quarantines) answers that arrive earlier than this after the challenge was issued
const MIN_CHALLENGE_AGE_MS = 3200;

(function () {
  handleRedirectFlags('contact', {
    __success: 'Vielen Dank! Ihre Nachricht wurde erfolgreich versendet. Ich melde mich zeitnah bei Ihnen.',
    rate: 'Bitte einen Moment warten, bevor das Formular erneut abgesendet wird.',
    mail: 'Der Versand der E‑Mail ist fehlgeschlagen. Ein erneuter Versuch lohnt sich.',
    db: 'Es ist ein technischer Fehler aufgetreten. Bitte versuchen Sie es in wenigen Minuten erneut.',
  });

  const form = document.querySelector<HTMLFormElement>('form[name="form_contact"]');
  if (!form) return;

  const challengeField = form.querySelector<HTMLInputElement>('[name="form_contact[challenge]"]');
  const solutionField = form.querySelector<HTMLInputElement>('[name="form_contact[solution]"]');

  // Measured from page load / arrival of the challenge, which is never earlier than its issue time
  let challengeReceivedAt = Date.now();
  let pendingSolution: Promise<string> | null = null;

  // The spam challenge is solved once per challenge, in the background.
  function prepareSolution(): Promise<string> {
    if (pendingSolution === null) {
      const waitMs = Math.max(0, challengeReceivedAt + MIN_CHALLENGE_AGE_MS - Date.now());
      const wait = new Promise<void>((resolve) => window.setTimeout(resolve, waitMs));

      pendingSolution = Promise.all([solveChallenge(challengeField?.value ?? ''), wait]).then(([solution]) => solution);
    }

    return pendingSolution;
  }

  // Every challenge is valid for a single submission; the server hands out the next one.
  function applyChallenge(challenge: string): void {
    if (challengeField) challengeField.value = challenge;
    if (solutionField) solutionField.value = '';
    challengeReceivedAt = Date.now();
    pendingSolution = null;
  }

  // Start as soon as the visitor touches the form so that sending is not delayed.
  form.addEventListener('focusin', () => {
    void prepareSolution();
  });

  enhanceAjaxForm(form, {
    beforeSubmit: async () => {
      if (solutionField) solutionField.value = await prepareSolution();
    },
    onResponse: (_form, data) => {
      if (data.challenge) applyChallenge(data.challenge);
    },
  });
})();
