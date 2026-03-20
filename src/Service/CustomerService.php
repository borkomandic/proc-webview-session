<?php declare(strict_types=1);

namespace ProCoders\WebViewCustomerSession\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Manages customer upsert and session token operations for the WebView flow.
 *
 * API request field mapping:
 *   appUserId       — unique user identifier from the mobile app
 *   customerGroupId — (optional) Shopware customer group UUID; uses default group if omitted
 *   email           — customer email
 *   firstName    — customer first name
 *   lastName     — customer last name
 *   languageCode — BCP 47 language code (e.g. "hr-HR")
 *
 * @author Borko Mandić — ProCoders (procode.rs)
 */
class CustomerService
{
    public const STATUS_CREATED    = 'created';
    public const STATUS_MODIFIED   = 'modified';
    public const STATUS_UNMODIFIED = 'unmodified';

    public function __construct(
        private readonly SystemConfigService        $config,
        private readonly Connection                 $connection,
        private readonly AccountService             $accountService,
        private readonly RequestStack               $requestStack,
        private readonly EntityRepository           $customerRepository,
        private readonly EntityRepository           $languageRepository,
        private readonly SalesChannelService        $salesChannelService,
        private readonly CustomerPostCreatedService $customerPostCreatedService,
    ) {}

    public function upsertCustomer(Request $request, Context $context): array
    {
        $upsertCustomerData = $this->mapCustomerParams($request, $context);

        $existingCustomerEntity = current(
            $this->customerRepository->search(new Criteria([$upsertCustomerData['id']]), $context)->getElements()
        );

        $writeStatus = self::STATUS_UNMODIFIED;

        if ($existingCustomerEntity) {
            if (!$this->assocsContained($upsertCustomerData, $existingCustomerEntity->getVars())) {
                $this->customerRepository->update([$upsertCustomerData], $context);
                $writeStatus = self::STATUS_MODIFIED;
            }
        } else {
            $upsertCustomerData['password']                 = $this->generatePassword(12);
            $upsertCustomerData['defaultBillingAddressId']  = $this->config->get('PCWebViewCustomerSession.config.defaultAddressId');
            $upsertCustomerData['defaultShippingAddressId'] = $this->config->get('PCWebViewCustomerSession.config.defaultAddressId');

            $this->customerRepository->upsert([$upsertCustomerData], $context);
            $this->customerPostCreatedService->customerPostCreatedActions($upsertCustomerData['id'], $context);
            $writeStatus = self::STATUS_CREATED;
        }

        $loginToken = $this->loginCustomerById($upsertCustomerData['id']);

        return [
            'status'  => 'success',
            'message' => "customer {$upsertCustomerData['id']} $writeStatus, and successfully logged in",
            'info'    => [
                'customerAction' => $writeStatus,
                'swCustomerId'   => $upsertCustomerData['id'],
                'swContextToken' => $loginToken,
            ],
        ];
    }

    public function getCustomerIdByToken(string $token): ?string
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('LOWER(HEX(customer_id)) AS customerId');
        $qb->from('sales_channel_api_context');
        $qb->where('token = :token');
        $qb->setParameter('token', $token);
        $data = $qb->executeQuery()->fetchAssociative();

        return array_key_exists('customerId', $data ?: []) ? $data['customerId'] : null;
    }

    public function getFrontendCookieToken(): ?string
    {
        $cookieName = $this->config->get('PCWebViewCustomerSession.config.frontendTokenCookieName');
        return $this->requestStack->getCurrentRequest()->cookies->get($cookieName) ?? null;
    }

    public function loginCustomerById(string $customerId): string
    {
        $salesChannelContext = $this->salesChannelService->createSalesChannelContextFromCustomerId($customerId);
        return $this->accountService->loginById($customerId, $salesChannelContext);
    }

    public function getTokenByCustomerId(string $customerId): ?string
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('token');
        $qb->from('sales_channel_api_context');
        $qb->where('customer_id = :customer_id');
        $qb->setParameter('customer_id', Uuid::fromHexToBytes($customerId));
        $data = $qb->executeQuery()->fetchAssociative();

        return array_key_exists('token', $data ?: []) ? $data['token'] : null;
    }

    private function mapCustomerParams(Request $request, Context $context): array
    {
        $params = [
            'id'             => $this->appUserIdToSwId($request->request->get('appUserId')),
            'salesChannelId' => $this->config->get('PCWebViewCustomerSession.config.salesChannelId'),
            'languageId'     => $this->getLanguageIdByCode($request->request->get('languageCode'), $context),
            'customerNumber' => $request->request->get('appUserId'),
            'firstName'      => $request->request->get('firstName'),
            'lastName'       => $request->request->get('lastName'),
            'email'          => $request->request->get('email'),
            'accountType'    => 'private',
        ];

        $customerGroupId = $request->request->get('customerGroupId');
        if (!empty($customerGroupId)) {
            $params['groupId'] = $customerGroupId;
        }

        return $params;
    }

    private function appUserIdToSwId(string $appUserId): string
    {
        return md5($appUserId);
    }

    private function getLanguageIdByCode(string $languageCode, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.locale.code', $languageCode));

        $languageEntity = current($this->languageRepository->search($criteria, $context)->getElements());
        return $languageEntity ? $languageEntity->getId() : null;
    }

    private function generatePassword(int $charsTotal): string
    {
        $chars = '!@#$%*&abcdefghijklmnpqrstuwxyzABCDEFGHJKLMNPQRSTUWXYZ23456789';
        return substr(str_shuffle($chars), 0, $charsTotal);
    }

    private function assocsContained(array $subset, array $superset): bool
    {
        foreach ($subset as $key => $value) {
            if (!array_key_exists($key, $superset) || $superset[$key] !== $value) {
                return false;
            }
        }
        return true;
    }
}
