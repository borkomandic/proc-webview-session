<?php declare(strict_types=1);

namespace ProCoders\WebViewCustomerSession\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Provides sales channel context utilities required by the WebView session flow.
 *
 * @author Borko Mandić — ProCoders (procode.rs)
 */
class SalesChannelService
{
    public function __construct(
        private readonly SystemConfigService       $config,
        private readonly EntityRepository          $salesChannelRepository,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    ) {}

    public function createSalesChannelContextFromCustomerId(string $customerId): SalesChannelContext
    {
        $salesChannelId = $this->config->get('PCWebViewCustomerSession.config.salesChannelId');

        return $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            $salesChannelId,
            [SalesChannelContextService::CUSTOMER_ID => $customerId]
        );
    }

    public function getDefaultSalesChannel(): SalesChannelEntity
    {
        $salesChannelId = $this->config->get('PCWebViewCustomerSession.config.salesChannelId');

        return $this->salesChannelRepository->search(
            new Criteria([$salesChannelId]),
            Context::createDefaultContext()
        )->first();
    }
}
