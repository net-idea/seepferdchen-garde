// Bootstrap: selective SCSS import (see assets/styles/bootstrap.scss)
import './styles/bootstrap.scss';
import './styles/fonts.css';
import './styles/carousel.css';

// Site theme (colors, navbar, footer, forms)
import './styles/theme.css';

// Print styles
import './styles/print.css';

// Bootstrap JS: only the plugins in use (no Popper, no dropdown/modal/tooltip …)
import 'bootstrap/js/dist/collapse';
import 'bootstrap/js/dist/carousel';

// Stateless CSRF double-submit cookie (Symfony SameOriginCsrfTokenManager)
import './scripts/csrf-protection.js';

// Import TypeScript
import './scripts/contacts.ts';
import './scripts/contact-form.ts';
import './scripts/booking-form.ts';
