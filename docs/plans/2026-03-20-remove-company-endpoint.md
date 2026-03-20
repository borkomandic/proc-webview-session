# Remove Company Endpoint & Simplify Customer Group Assignment Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Remove the `/api/pc-webview-company` endpoint and `companyId` field entirely, replacing it with an optional `customerGroupId` field on `/api/pc-webview-customer` that assigns the customer to an existing Shopware customer group.

**Architecture:** Delete all company-related classes (controller, service, events, subscriber). Modify `PCCustomerController` and `CustomerService` to accept an optional `customerGroupId` (raw UUID) instead of a required `companyId` (MD5-hashed). When `customerGroupId` is omitted, Shopware's default behavior applies.

**Tech Stack:** PHP 8.2, Shopware 6.7.3.0, Symfony DI (XML), Doctrine DBAL

---

### Task 1: Delete company-related files

**Files:**
- Delete: `src/Administration/Controller/PCCompanyController.php`
- Delete: `src/Service/CompanyService.php`
- Delete: `src/Event/PCCompanyDuplicatedEvent.php`
- Delete: `src/Event/PCCompanyUpsertFailedEvent.php`
- Delete: `src/Storefront/Subscriber/BusinessEventCollectorSubscriber.php`

**Step 1: Delete the files**

```bash
rm src/Administration/Controller/PCCompanyController.php
rm src/Service/CompanyService.php
rm src/Event/PCCompanyDuplicatedEvent.php
rm src/Event/PCCompanyUpsertFailedEvent.php
rm src/Storefront/Subscriber/BusinessEventCollectorSubscriber.php
```

Run from: `custom/plugins/PCWebViewCustomerSession/`

**Step 2: Commit**

```bash
git add -A
git commit -m "remove: company endpoint, service, events, and business event subscriber"
```

---

### Task 2: Clean up DI — controllers.xml

**Files:**
- Modify: `src/DependencyInjection/controllers.xml`

**Step 1: Remove `PCCompanyController` service and `CompanyService` argument from `PCCustomerController`**

Replace the entire file content with:

```xml
<?xml version="1.0" ?>

<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">

    <services>

        <service id="ProCoders\WebViewCustomerSession\Administration\Controller\PCCustomerController" public="true">
            <argument type="service" id="ProCoders\WebViewCustomerSession\Service\CustomerService"/>
            <argument type="service" id="ProCoders\WebViewCustomerSession\Service\HttpResponseService"/>
            <call method="setContainer">
                <argument type="service" id="service_container"/>
            </call>
        </service>

    </services>
</container>
```

**Step 2: Commit**

```bash
git add src/DependencyInjection/controllers.xml
git commit -m "remove: PCCompanyController DI entry and CompanyService arg from PCCustomerController"
```

---

### Task 3: Clean up DI — services.xml

**Files:**
- Modify: `src/DependencyInjection/services.xml`

**Step 1: Remove the `CompanyService` service block**

Remove these lines from `services.xml`:

```xml
<service id="ProCoders\WebViewCustomerSession\Service\CompanyService" public="true">
    <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
    <argument type="service" id="customer_group.repository"/>
    <argument type="service" id="Symfony\Contracts\EventDispatcher\EventDispatcherInterface"/>
    <argument type="service" id="ProCoders\WebViewCustomerSession\Service\SalesChannelService"/>
</service>
```

**Step 2: Commit**

```bash
git add src/DependencyInjection/services.xml
git commit -m "remove: CompanyService DI registration"
```

---

### Task 4: Clean up DI — subscribers.xml

**Files:**
- Modify: `src/DependencyInjection/subscribers.xml`

**Step 1: Remove `BusinessEventCollectorSubscriber` service block**

Remove these lines from `subscribers.xml`:

```xml
<service id="ProCoders\WebViewCustomerSession\Storefront\Subscriber\BusinessEventCollectorSubscriber">
    <argument type="service" id="ProCoders\WebViewCustomerSession\Framework\Event\BusinessEventCollector"/>
    <tag name="kernel.event_subscriber"/>
</service>
```

**Step 2: Commit**

```bash
git add src/DependencyInjection/subscribers.xml
git commit -m "remove: BusinessEventCollectorSubscriber DI registration"
```

---

### Task 5: Update `PCCustomerController`

**Files:**
- Modify: `src/Administration/Controller/PCCustomerController.php`

**Step 1: Replace file content**

```php
<?php declare(strict_types=1);

namespace ProCoders\WebViewCustomerSession\Administration\Controller;

use ProCoders\WebViewCustomerSession\Service\CustomerService;
use ProCoders\WebViewCustomerSession\Service\HttpResponseService;
use Shopware\Core\Framework\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class PCCustomerController extends AbstractController
{
    public function __construct(
        private readonly CustomerService     $customerService,
        private readonly HttpResponseService $httpResponseService,
    ) {}

    #[Route('/api/pc-webview-customer', name: 'api.pc_webview.customer', methods: ['POST'])]
    public function upsertCustomer(Request $request, Context $context): JsonResponse
    {
        $requiredFields = ['appUserId', 'email', 'firstName', 'lastName', 'languageCode'];

        foreach ($requiredFields as $field) {
            if (empty($request->request->get($field))) {
                $this->httpResponseService->returnHttpResponse(400,
                    "The following fields are mandatory: $field");
            }
        }

        $result = $this->customerService->upsertCustomer($request, $context);

        return new JsonResponse($result);
    }
}
```

**Step 2: Commit**

```bash
git add src/Administration/Controller/PCCustomerController.php
git commit -m "remove: companyId requirement from PCCustomerController, drop CompanyService dependency"
```

---

### Task 6: Update `CustomerService`

**Files:**
- Modify: `src/Service/CustomerService.php`

**Step 1: Update `mapCustomerParams` to replace `companyId` with optional `customerGroupId`**

Replace the `mapCustomerParams` method:

```php
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
```

**Step 2: Commit**

```bash
git add src/Service/CustomerService.php
git commit -m "feat: replace companyId with optional customerGroupId in CustomerService"
```

---

### Task 7: Verify the plugin loads without errors

**Step 1: Clear Shopware cache**

```bash
docker exec sw-6-7-3-0 php bin/console cache:clear
```

**Step 2: Check for any PHP errors in logs**

```bash
docker exec sw-6-7-3-0 tail -50 var/log/dev.log
```

Expected: no errors related to missing classes or DI container.

**Step 3: Test the endpoint**

```bash
curl -X POST http://procoders-sw.local:8080/api/pc-webview-customer \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "appUserId": "test-user-1",
    "email": "test@example.com",
    "firstName": "Test",
    "lastName": "User",
    "languageCode": "en-GB"
  }'
```

Expected: `{"status":"success",...}` with no `companyId` required.

**Step 4: Test with optional `customerGroupId`**

```bash
curl -X POST http://procoders-sw.local:8080/api/pc-webview-customer \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "appUserId": "test-user-2",
    "email": "test2@example.com",
    "firstName": "Test",
    "lastName": "Two",
    "languageCode": "en-GB",
    "customerGroupId": "<existing-customer-group-uuid>"
  }'
```

Expected: customer created and assigned to the specified group.
