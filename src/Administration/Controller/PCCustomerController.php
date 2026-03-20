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
