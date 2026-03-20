<?php declare(strict_types=1);

namespace ProCoders\WebViewSession\Service;

use Shopware\Core\Framework\Context;

/**
 * Handles post-creation hook actions for newly registered customers.
 * Extend this class or dispatch events here to integrate third-party services
 * (e.g. loyalty platforms, CRM, external identity providers).
 *
 * @author Borko Mandić — ProCoders (procode.rs)
 */
class CustomerPostCreatedService
{
    public function customerPostCreatedActions(string $customerId, Context $context): void
    {
        // Hook for post-creation integrations.
        // Implement as needed per project (loyalty enrollment, CRM sync, etc.).
    }
}
