<?php declare(strict_types=1);

namespace ProCoders\WebViewCustomerSession\Storefront\Subscriber;

use ProCoders\WebViewCustomerSession\Service\CustomerService;
use ProCoders\WebViewCustomerSession\Service\HttpResponseService;
use ProCoders\WebViewCustomerSession\Service\SalesChannelService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Intercepts all storefront responses to enforce WebView session integrity.
 *
 * Flow:
 *   1. Skips non-frontend routes.
 *   2. Returns 503 if the sales channel is in maintenance and the client IP
 *      is not whitelisted.
 *   3. Disables HTTP caching on the homepage to prevent stale session state.
 *   4. Reads the SW context token from the configured cookie.
 *   5. Looks up the customer ID in sales_channel_api_context by cookie token.
 *   6a. Token found with customer → valid session, allow through.
 *   6b. Token not found in DB → 401 (app re-initiates the session flow).
 *
 * @author Borko Mandić — ProCoders (procode.rs)
 */
class PCSessionHandlerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CustomerService     $customerService,
        private readonly SalesChannelService $salesChannelService,
        private readonly HttpResponseService $httpResponseService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request      = $event->getRequest();
        $response     = $event->getResponse();
        $requestRoute = $request->get('_route');

        if (!$requestRoute) {
            return;
        }

        if (!str_starts_with($requestRoute, 'frontend.')) {
            return;
        }

        // Maintenance mode guard
        $salesChannel = $this->salesChannelService->getDefaultSalesChannel();
        if ($salesChannel->isMaintenance() && !in_array($request->getClientIp(), $salesChannel->getMaintenanceIpWhitelist())) {
            $this->httpResponseService->returnHttpResponse(503, 'System is under maintenance. Please try again later.');
        }

        // Disable HTTP caching on homepage to prevent stale session state in WebView
        if ($requestRoute === 'frontend.home.page') {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        }

        $cookieToken = $this->customerService->getFrontendCookieToken();

        // No cookie token present — app has not yet injected the session cookie
        if (!$cookieToken) {
            $this->httpResponseService->returnHttpResponse(401,
                'No sw-context-token cookie found. Initialize the session via POST /api/pc-webview-customer.');
        }

        // Check whether the cookie token maps to an active customer session in DB
        $customerId = $this->customerService->getCustomerIdByToken($cookieToken);

        if ($customerId) {
            // Valid customer session — allow the request through
            return;
        }

        // Cookie token not found in DB or has no customer — session is invalid or expired
        $this->httpResponseService->returnHttpResponse(401,
            'No active customer session found for the provided cookie token. Re-initialize via POST /api/pc-webview-customer.');
    }
}
