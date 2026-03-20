<?php declare(strict_types=1);

namespace ProCoders\WebViewCustomerSession\Administration\Controller;

use ProCoders\WebViewCustomerSession\Service\CompanyService;
use ProCoders\WebViewCustomerSession\Service\CustomerService;
use ProCoders\WebViewCustomerSession\Service\HttpResponseService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles customer upsert and session initialization requests from the mobile app.
 *
 * POST /api/pc-webview-customer
 *
 * Required fields: appUserId, companyId, email, firstName, lastName, languageCode
 *
 * Returns swContextToken on success — the app passes this as a cookie when
 * opening the WebView.
 *
 * @author Borko Mandić — ProCoders (procode.rs)
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('administration')]
class PCCustomerController extends AbstractController
{
    public function __construct(
        private readonly CustomerService     $customerService,
        private readonly CompanyService      $companyService,
        private readonly HttpResponseService $httpResponseService,
    ) {}

    #[Route(path: '/api/pc-webview-customer', name: 'api.pc_webview_customer', methods: ['POST'])]
    public function pcWebviewCustomer(Request $request, Context $context): Response
    {
        $requiredFields = ['appUserId', 'companyId', 'email', 'firstName', 'lastName', 'languageCode'];
        $missingFields  = [];

        foreach ($requiredFields as $field) {
            if (empty($request->request->get($field))) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $this->httpResponseService->returnHttpResponse(400,
                'The following fields are mandatory: ' . implode(', ', $missingFields));
        }

        if (!$this->companyService->getCustomerGroupByExternalId($request->request->get('companyId'), $context)) {
            $this->httpResponseService->returnHttpResponse(400,
                'Non-existent companyId: ' . $request->request->get('companyId'));
        }

        $result = $this->customerService->upsertCustomer($request, $context);

        return new JsonResponse($result, 200);
    }
}
