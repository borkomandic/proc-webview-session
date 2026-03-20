<?php declare(strict_types=1);

namespace ProCoders\WebViewCustomerSession\Service;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Sends an HTTP JSON response and terminates execution.
 *
 * @author Borko Mandić — ProCoders (procode.rs)
 */
class HttpResponseService
{
    public function returnHttpResponse(int $statusCode, string $message): void
    {
        $response = new JsonResponse([
            'status'  => $statusCode >= 400 ? 'error' : 'success',
            'message' => $message,
        ], $statusCode);
        $response->send();
        exit;
    }
}
