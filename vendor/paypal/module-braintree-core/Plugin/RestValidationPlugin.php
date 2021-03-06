<?php

declare(strict_types=1);

namespace PayPal\Braintree\Plugin;

use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\ReCaptchaWebapiApi\Api\WebapiValidationConfigProviderInterface;
use Magento\ReCaptchaValidationApi\Api\ValidatorInterface;
use Magento\ReCaptchaWebapiApi\Model\Data\Endpoint;
use Magento\Webapi\Controller\Rest\Router;
use Magento\ReCaptchaWebapiApi\Model\Data\EndpointFactory;
use Magento\Webapi\Controller\Rest\RequestValidator;
use PayPal\Braintree\Model\ApplePay\Ui\ConfigProvider as ApplePay;
use PayPal\Braintree\Model\GooglePay\Ui\ConfigProvider as GooglePay;

/**
 * Enable ReCaptcha validation for RESTful web API.
 */
class RestValidationPlugin
{
    /**
     * @var ValidatorInterface
     */
    private $recaptchaValidator;

    /**
     * @var WebapiValidationConfigProviderInterface
     */
    private $configProvider;

    /**
     * @var RestRequest
     */
    private $request;

    /**
     * @var Router
     */
    private $restRouter;

    /**
     * @var EndpointFactory
     */
    private $endpointFactory;

    /**
     * @param ValidatorInterface $recaptchaValidator
     * @param WebapiValidationConfigProviderInterface $configProvider
     * @param RestRequest $request
     * @param Router $restRouter
     * @param EndpointFactory $endpointFactory
     */
    public function __construct(
        ValidatorInterface $recaptchaValidator,
        WebapiValidationConfigProviderInterface $configProvider,
        RestRequest $request,
        Router $restRouter,
        EndpointFactory $endpointFactory
    ) {
        $this->recaptchaValidator = $recaptchaValidator;
        $this->configProvider = $configProvider;
        $this->request = $request;
        $this->restRouter = $restRouter;
        $this->endpointFactory = $endpointFactory;
    }

    /**
     * Validate ReCaptcha if needed.
     *
     * @param RequestValidator $subject
     * @param callable $proceed
     * @throws WebapiException
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundValidate(RequestValidator $subject, callable $proceed): void
    {
        $request = clone $this->request;
        $proceed();
        $route = $this->restRouter->match($request);
        $endpoint = $this->endpointFactory->create([
            'class' => $route->getServiceClass(),
            'method' => $route->getServiceMethod(),
            'name' => $route->getRoutePath()
        ]);
        $config = $this->configProvider->getConfigFor($endpoint);
        if ($config) {
            if (isset($this->request->getRequestData()['paymentMethod']['method'])
                && in_array($this->request->getRequestData()['paymentMethod']['method'],
                    [ApplePay::METHOD_CODE, GooglePay::METHOD_CODE])) {
                return;
            }
            $value = (string)$this->request->getHeader('X-ReCaptcha');
            if (!$this->recaptchaValidator->isValid($value, $config)->isValid()) {
                throw new WebapiException(__('ReCaptcha validation failed, please try again'));
            }
        }
    }
}
