<?php declare(strict_types=1);

namespace ProCoders\WebViewSession\Storefront\Subscriber;

use ProCoders\WebViewSession\Service\CustomerService;
use Shopware\Core\System\SystemConfig\Event\SystemConfigMultipleChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Handles debugging actions triggered via admin system config.
 *
 * When autoLogInEnabled is toggled ON and saved in the admin panel:
 *   1. The switch is immediately reset to OFF (one-shot trigger).
 *   2. A browser cookie is set with the SW context token for the selected customer,
 *      allowing a developer to simulate a WebView session from a regular browser.
 *
 * @author Borko Mandić — ProCoders (procode.rs)
 */
class SystemConfigSubscriber implements EventSubscriberInterface
{
    private const CONFIG_PREFIX = 'ProcWebViewSession.config.';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly CustomerService     $customerService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigMultipleChangedEvent::class => 'onSystemConfigMultipleChanged',
        ];
    }

    public function onSystemConfigMultipleChanged(SystemConfigMultipleChangedEvent $event): void
    {
        $config = $event->getConfig();

        if (!isset($config[self::CONFIG_PREFIX . 'autoLogInEnabled'])
            || $config[self::CONFIG_PREFIX . 'autoLogInEnabled'] !== true
        ) {
            return;
        }

        // Immediately reset the toggle — one-shot behaviour
        $this->systemConfigService->set(self::CONFIG_PREFIX . 'autoLogInEnabled', false);

        if (!isset($config[self::CONFIG_PREFIX . 'autoLogInCustomerId'], $config[self::CONFIG_PREFIX . 'frontendTokenCookieName'])) {
            return;
        }

        $customerId  = $config[self::CONFIG_PREFIX . 'autoLogInCustomerId'];
        $cookieName  = $config[self::CONFIG_PREFIX . 'frontendTokenCookieName'];
        $cookieValue = $this->customerService->getTokenByCustomerId($customerId)
            ?? $this->customerService->loginCustomerById($customerId);

        if ($cookieValue) {
            setcookie($cookieName, $cookieValue, time() + (86400 * 30), '/');
        }
    }
}
