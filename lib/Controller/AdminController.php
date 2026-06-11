<?php

declare(strict_types=1);

namespace OCA\jitsi\Controller;

use OCA\jitsi\AppInfo\Application;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;

class AdminController extends ApiController {
    public function __construct(
        IRequest $request,
        private IConfig $config,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * @AdminRequired
     * @NoCSRFRequired
     * 
     * Get a setting value
     * 
     * @param string $key Setting key
     * @param string|null $default Default value
     * @return DataResponse
     */
    public function getSetting(string $key, ?string $default = null): DataResponse {
        $value = $this->config->getAppValue(
            Application::APP_ID,
            $key,
            $default ?? ''
        );
        
        return new DataResponse([
            'key' => $key,
            'value' => $value,
            'success' => true
        ]);
    }

    /**
     * @AdminRequired
     * @NoCSRFRequired
     * 
     * Set a setting value
     * 
     * @param string $key Setting key
     * @param mixed $value Setting value (will be converted to string)
     * @return DataResponse
     */
    public function setSetting(string $key, $value): DataResponse {
        $this->config->setAppValue(
            Application::APP_ID,
            $key,
            (string)$value
        );
        
        return new DataResponse([
            'key' => $key,
            'value' => $value,
            'success' => true
        ]);
    }
}
