/**
 * Progressive enhancement for the booking and contact forms.
 *
 * Without JavaScript the forms POST to their classic action and the server answers with a
 * redirect (?submit=1 / ?error=…). With JavaScript the same payload is sent to the JSON API
 * (data-api-url) and the page is updated in place:
 *   - success  -> form gets display:none, the success container shows the server-rendered summary
 *   - invalid  -> field errors are rendered inline (Bootstrap .is-invalid / .invalid-feedback)
 *   - other    -> a general error alert
 */
import { generateCsrfHeaders, generateCsrfToken } from './csrf-protection.js';

export interface ApiResponse {
  status: 'ok' | 'invalid' | 'rate_limited' | 'db_error' | 'mail_error' | 'error' | string;
  message?: string;
  errors?: Record<string, string[]>;
  summaryHtml?: string | null;
}

export interface AjaxFormOptions {
  /** Called after a successful submission (form already hidden, success alert rendered). */
  onSuccess?: (form: HTMLFormElement, data: ApiResponse) => void;
}

export const GENERIC_ERROR = 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es in Kürze erneut oder kontaktieren Sie mich direkt.';

export function escapeHtml(text: string): string {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

export function showAlert(el: HTMLElement | null, html: string, cls: 'alert-success' | 'alert-danger'): void {
  if (!el) return;
  el.classList.remove('visually-hidden', 'alert-success', 'alert-danger');
  el.classList.add('alert', cls, 'mb-3');
  el.setAttribute('aria-hidden', 'false');
  el.setAttribute('role', 'alert');
  el.setAttribute('tabindex', '-1');
  el.innerHTML = html;
  window.setTimeout(() => {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    try {
      el.focus({ preventScroll: true });
    } catch {
      /* noop */
    }
  }, 0);
}

export function hideAlert(el: HTMLElement | null): void {
  if (!el) return;
  el.classList.add('visually-hidden');
  el.classList.remove('alert', 'alert-success', 'alert-danger', 'mb-3');
  el.setAttribute('aria-hidden', 'true');
  el.removeAttribute('role');
  el.innerHTML = '';
}

function clearFieldErrors(form: HTMLFormElement): void {
  form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
  form.querySelectorAll('[data-ajax-error]').forEach((el) => el.remove());
}

/** Find the element(s) that represent a form field: input/select/textarea or an expanded choice container. */
function fieldElements(form: HTMLFormElement, name: string): HTMLElement[] {
  const fullName = `${form.name}[${name}]`;
  const direct = Array.from(form.querySelectorAll<HTMLElement>(`[name="${fullName}"], [name="${fullName}[]"]`));
  if (direct.length > 0) return direct;
  const container = document.getElementById(`${form.name}_${name}`);
  return container ? [container] : [];
}

function applyFieldErrors(form: HTMLFormElement, errors: Record<string, string[]>): string[] {
  const global: string[] = [];

  Object.entries(errors).forEach(([name, messages]) => {
    if (name === '_global') {
      global.push(...messages);
      return;
    }

    const elements = fieldElements(form, name);
    if (elements.length === 0) {
      global.push(...messages);
      return;
    }

    elements.forEach((el) => el.classList.add('is-invalid'));

    const feedback = document.createElement('div');
    feedback.className = 'invalid-feedback d-block';
    feedback.setAttribute('data-ajax-error', '');
    feedback.textContent = messages.join(' ');

    // Place the message after the visual wrapper (floating label / check label), otherwise after the field
    const anchor = elements[elements.length - 1];
    const wrapper = anchor.closest('.form-floating') ?? (anchor.classList.contains('form-check-input') ? anchor.closest('.form-check')?.querySelector('label') ?? anchor : anchor);
    wrapper.insertAdjacentElement('afterend', feedback);
  });

  return global;
}

function focusFirstInvalid(form: HTMLFormElement): void {
  const first = form.querySelector<HTMLElement>('.is-invalid, :invalid');
  if (first) {
    first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    try {
      first.focus({ preventScroll: true });
    } catch {
      /* noop */
    }
  }
}

/**
 * Success state: only the confirmation container remains visible (screen) and printable (print.css).
 * The form gets display:none, everything marked data-hide-on-success disappears via body class.
 */
export function markSuccessState(form: HTMLFormElement): void {
  form.hidden = true;
  document.body.classList.add('form-success-shown');
  document.querySelectorAll<HTMLElement>('[data-hide-on-success]').forEach((el) => {
    el.hidden = true;
  });
}

export function enhanceAjaxForm(form: HTMLFormElement, opts: AjaxFormOptions = {}): void {
  const apiUrl = form.dataset.apiUrl;
  if (!apiUrl) return;

  const successEl = form.dataset.successTarget ? document.getElementById(form.dataset.successTarget) : null;
  const errorEl = form.dataset.errorTarget ? document.getElementById(form.dataset.errorTarget) : null;
  const submitBtn = form.querySelector<HTMLButtonElement>('button[type="submit"]');
  const submitLabel = submitBtn?.innerHTML ?? '';

  const setBusy = (busy: boolean): void => {
    if (!submitBtn) return;
    submitBtn.disabled = busy;
    submitBtn.setAttribute('aria-busy', busy ? 'true' : 'false');
    submitBtn.innerHTML = busy ? 'Wird gesendet…' : submitLabel;
  };

  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();

    clearFieldErrors(form);
    hideAlert(errorEl);

    if (!form.checkValidity()) {
      form.classList.add('was-validated');
      focusFirstInvalid(form);
      return;
    }

    setBusy(true);

    try {
      // Stateless CSRF: make sure the double-submit cookie + token are in place before reading the form data
      generateCsrfToken(form);
      const body = new FormData(form);

      const response = await fetch(apiUrl, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...generateCsrfHeaders(form),
        },
      });

      let data: ApiResponse;
      try {
        data = (await response.json()) as ApiResponse;
      } catch {
        data = { status: 'error' };
      }

      if (response.ok && data.status === 'ok') {
        form.classList.remove('was-validated');
        markSuccessState(form);
        const html = data.summaryHtml ?? `<p class="mb-0">${escapeHtml(data.message ?? '')}</p>`;
        showAlert(successEl, html, 'alert-success');
        if (successEl?.id) {
          window.history.replaceState({}, '', `${window.location.pathname}#${successEl.id}`);
        }
        opts.onSuccess?.(form, data);
        return;
      }

      if (response.status === 422 && data.errors) {
        const global = applyFieldErrors(form, data.errors);
        const parts = [escapeHtml(data.message ?? GENERIC_ERROR), ...global.map(escapeHtml)];
        showAlert(errorEl, parts.map((p) => `<p class="mb-0">${p}</p>`).join(''), 'alert-danger');
        window.setTimeout(() => focusFirstInvalid(form), 50);
        return;
      }

      showAlert(errorEl, `<p class="mb-0">${escapeHtml(data.message ?? GENERIC_ERROR)}</p>`, 'alert-danger');
    } catch {
      showAlert(errorEl, `<p class="mb-0">${GENERIC_ERROR}</p>`, 'alert-danger');
    } finally {
      setBusy(false);
    }
  });
}

/** Legacy fallback: after a classic redirect (no-JS submit) the server puts ?submit=1 / ?error=… in the URL. */
export function handleRedirectFlags(prefix: 'booking' | 'contact', errorMessages: Record<string, string>): void {
  const params = new URLSearchParams(window.location.search);
  const successEl = document.getElementById(`${prefix}-success`);
  const errorEl = document.getElementById(`${prefix}-error`);
  const hasSent = params.has('submit') || params.has('sent');
  const hasError = params.has('error');

  if (hasSent && successEl && !successEl.querySelector(`#${prefix}-success-summary`) && successEl.textContent?.trim() === '') {
    showAlert(successEl, `<p class="mb-0">${escapeHtml(errorMessages.__success ?? 'Vielen Dank!')}</p>`, 'alert-success');
  } else if (hasError && errorEl && errorEl.textContent?.trim() === '') {
    const e = params.get('error') ?? '';
    showAlert(errorEl, `<p class="mb-0">${escapeHtml(errorMessages[e] ?? GENERIC_ERROR)}</p>`, 'alert-danger');
  }

  if (hasSent && successEl) {
    // the server rendered the summary; hide the (empty) form and the intro like in the AJAX flow
    const form = document.querySelector<HTMLFormElement>(`form[data-success-target="${prefix}-success"]`);
    if (form) markSuccessState(form);
  }

  if (hasSent || hasError) {
    const hash = window.location.hash || (hasSent ? `#${prefix}-success` : `#${prefix}-error`);
    window.history.replaceState({}, '', window.location.pathname + hash);
  }
}
