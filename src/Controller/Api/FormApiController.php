<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\FormBookingEntity;
use App\Service\FormBookingService;
use App\Service\FormContactService;
use App\Service\FormSubmissionResult;
use App\Service\FormSubmissionStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON endpoints used by the AJAX-enhanced forms. They accept the very same
 * form payload as the classic POST (same Symfony form types, same CSRF token),
 * so the non-JS fallback and the API can never drift apart.
 */
#[Route(path: '/api', name: 'api_')]
final class FormApiController extends AbstractController
{
    #[Route(path: '/booking', name: 'booking', methods: ['POST'])]
    public function booking(Request $request, FormBookingService $service): JsonResponse
    {
        $result = $service->processRequest($request);

        return $this->respond(
            $result,
            'Vielen Dank! Ihre Anfrage ist eingegangen. Bitte bestätigen Sie sie über den Link in der E‑Mail, die Sie soeben erhalten haben.',
            fn (FormBookingEntity $booking): string => $this->renderView('_partials/booking_summary.html.twig', ['booking' => $booking]),
        );
    }

    #[Route(path: '/contact', name: 'contact', methods: ['POST'])]
    public function contact(Request $request, FormContactService $service): JsonResponse
    {
        $result = $service->processRequest($request);

        return $this->respond(
            $result,
            'Vielen Dank! Ihre Nachricht wurde erfolgreich versendet. Ich melde mich zeitnah bei Ihnen.',
        );
    }

    /**
     * @param (\Closure(object): string)|null $summaryRenderer renders an HTML summary for the "ok" case
     */
    private function respond(?FormSubmissionResult $result, string $successMessage, ?\Closure $summaryRenderer = null): JsonResponse
    {
        if (null === $result) {
            return $this->json(
                ['status' => 'error', 'message' => 'Es wurden keine Formulardaten übermittelt.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        return match ($result->status) {
            FormSubmissionStatus::Ok => $this->json([
                'status'      => 'ok',
                'message'     => $successMessage,
                'summaryHtml' => null !== $summaryRenderer && null !== $result->data ? $summaryRenderer($result->data) : null,
            ]),
            // Do not tell bots they were detected; a real user cannot hit this path anymore (see honeypot notes).
            FormSubmissionStatus::Spam    => $this->json(['status' => 'ok', 'message' => $successMessage, 'summaryHtml' => null]),
            FormSubmissionStatus::Invalid => $this->json([
                'status'  => 'invalid',
                'message' => 'Bitte prüfen Sie Ihre Eingaben. Die markierten Felder sind unvollständig oder ungültig.',
                'errors'  => $result->errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY),
            FormSubmissionStatus::RateLimited => $this->json([
                'status'  => 'rate_limited',
                'message' => 'Bitte warten Sie einen Moment, bevor Sie das Formular erneut absenden.',
            ], Response::HTTP_TOO_MANY_REQUESTS),
            FormSubmissionStatus::DbError => $this->json([
                'status'  => 'db_error',
                'message' => 'Es ist ein technischer Fehler aufgetreten und Ihre Angaben konnten nicht gespeichert werden. Bitte versuchen Sie es in wenigen Minuten erneut oder kontaktieren Sie mich direkt.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR),
            FormSubmissionStatus::MailError => $this->json([
                'status'  => 'mail_error',
                'message' => 'Ihre Angaben wurden gespeichert, aber die Bestätigungs‑E‑Mail konnte nicht versendet werden. Ich melde mich umgehend bei Ihnen. Falls Sie innerhalb von 24 Stunden nichts hören, kontaktieren Sie mich bitte direkt.',
            ], Response::HTTP_BAD_GATEWAY),
        };
    }
}
