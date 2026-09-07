<?php
declare(strict_types=1);

namespace App\Service;

final readonly class FormSubmissionResult
{
    /**
     * @param array<string, list<string>> $errors field name => messages ("_global" for form-level errors)
     */
    private function __construct(
        public FormSubmissionStatus $status,
        public ?object $data = null,
        public array $errors = [],
    ) {
    }

    public static function ok(object $data): self
    {
        return new self(FormSubmissionStatus::Ok, $data);
    }

    public static function spam(): self
    {
        return new self(FormSubmissionStatus::Spam);
    }

    /**
     * @param array<string, list<string>> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(FormSubmissionStatus::Invalid, null, $errors);
    }

    public static function rateLimited(): self
    {
        return new self(FormSubmissionStatus::RateLimited);
    }

    public static function dbError(object $data): self
    {
        return new self(FormSubmissionStatus::DbError, $data);
    }

    /** The record was saved, only the e-mail failed. */
    public static function mailError(object $data): self
    {
        return new self(FormSubmissionStatus::MailError, $data);
    }

    public function isOk(): bool
    {
        return FormSubmissionStatus::Ok === $this->status;
    }
}
