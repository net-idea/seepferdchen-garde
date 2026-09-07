<?php
declare(strict_types=1);

namespace App\Service;

/**
 * Outcome of a form submission (booking or contact), independent of how the
 * result is delivered (classic redirect or JSON API).
 */
enum FormSubmissionStatus: string
{
    case Ok = 'ok';
    case Spam = 'spam';
    case Invalid = 'invalid';
    case RateLimited = 'rate_limited';
    case DbError = 'db_error';
    case MailError = 'mail_error';
}
