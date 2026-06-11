# NC32/NC33 Compatibility - Technical Fix Guide

## Quick Navigation

1. [Critical Issues - Must Fix](#critical-issues)
2. [High Priority - Fix Soon](#high-priority)
3. [Medium Priority - Plan Updates](#medium-priority)
4. [Testing Guide](#testing-guide)

---

## CRITICAL ISSUES

### Issue #1: Update info.xml Version Constraint

**File**: `appinfo/info.xml`  
**Line**: 27

**Current (BROKEN)**:
```xml
<dependencies>
    <nextcloud min-version="25" max-version="31"/>
</dependencies>
```

**Fixed**:
```xml
<dependencies>
    <nextcloud min-version="25" max-version="33"/>
</dependencies>
```

**Why**: App declaration explicitly blocks NC32 and NC33.

**Testing**:
```bash
# After updating, test:
1. Install on NC32 instance - should not be blocked
2. Install on NC33 instance - should not be blocked
```

---

### Issue #2: Replace Deprecated OCP.AppConfig API

**File**: `src/Admin.vue`  
**Lines**: 244-268

#### CURRENT CODE (BROKEN)

```javascript
// Lines 244-250
async updateSetting(name, value) {
    try {
        await new Promise((resolve, reject) =>
            OCP.AppConfig.setValue('jitsi', name, value, {
                success: resolve,
                error: reject,
            })
        )
    } catch (e) {
        this.error = this.t('jitsi', 'Failed to save settings')
        throw e
    }
}

// Lines 256-268
async loadSetting(name, defaultValue = null) {
    try {
        const resDocument = await new Promise((resolve, reject) =>
            OCP.AppConfig.getValue('jitsi', name, defaultValue, {
                success: resolve,
                error: reject,
            })
        )
        if (resDocument.querySelector('status').textContent !== 'ok') {
            this.errorMessage = this.t('jitsi', 'Failed to load settings')
            console.error('Failed request', resDocument)
            return
        }
        const dataEl = resDocument.querySelector('data')
        return dataEl.firstElementChild.textContent
    } catch (e) {
        this.errorMessage = this.t('jitsi', 'Failed to load settings')
        throw e
    }
}
```

#### SOLUTION OPTION A: Create PHP API Endpoint (Recommended)

**File**: `lib/Controller/AdminController.php` (NEW FILE)

```php
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
     * @NoAdminRequired
     * @NoCSRFRequired
     * 
     * Get a single setting value
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
            'value' => $value
        ]);
    }

    /**
     * @NoAdminRequired (Make this admin-only!)
     * 
     * Set a setting value
     * 
     * @param string $key Setting key
     * @param string $value Setting value
     * @return DataResponse
     */
    public function setSetting(string $key, string $value): DataResponse {
        $this->config->setAppValue(
            Application::APP_ID,
            $key,
            $value
        );
        
        return new DataResponse([
            'key' => $key,
            'value' => $value
        ]);
    }
}
```

**File**: `appinfo/routes.php`

```php
<?php

declare(strict_types=1);

return [
    'routes' => [
        // ... existing routes ...
        
        // Admin settings API
        ['name' => 'admin#getSetting', 'url' => '/api/admin/settings/{key}', 'verb' => 'GET'],
        ['name' => 'admin#setSetting', 'url' => '/api/admin/settings', 'verb' => 'POST'],
    ],
];
```

**File**: `src/Admin.vue` (UPDATED)

```javascript
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// Replace the entire methods section with:
methods: {
    async submit() {
        this.sanitise()
        this.validate()

        if (this.hasError) {
            return
        }

        this.saving = true
        this.saved = false

        try {
            await Promise.all([
                this.updateSetting('jitsi_server_url', this.serverUrl),
                this.updateSetting('jwt_secret', this.jwtSecret),
                this.updateSetting('jwt_app_id', this.jwtAppId),
                this.updateSetting('jwt_audience', this.jwtAudience),
                this.updateSetting('jwt_issuer', this.jwtIssuer),
                this.updateSetting('help_link', this.helpLink),
                this.updateSetting('display_join_using_the_jitsi_app', this.displayJoinUsingTheJitsiApp),
            ])
            
            this.saving = false
            this.saved = true
        } catch (error) {
            console.error('Failed to save settings:', error)
            this.errorMessage = this.t('jitsi', 'Failed to save settings')
            this.saving = false
        }
    },
    
    sanitise() {
        if (this.serverUrl && !this.serverUrl.endsWith('/')) {
            this.serverUrl += '/'
        }
    },
    
    validate() {
        this.serverUrlStatus = false
        this.serverUrlMessage = ''

        if (!this.serverUrl) {
            this.serverUrlStatus = 'error'
            this.serverUrlMessage = this.t('jitsi', 'Please provide a Jitsi instance URL')
        }

        if (!this.serverUrl.startsWith('https://')) {
            this.serverUrlStatus = 'error'
            this.serverUrlMessage = this.t('jitsi', 'The server URL must start with https://')
        }

        if (this.serverUrl === 'https://meet.jit.si/') {
            this.serverUrlStatus = 'warning'
            this.serverUrlMessage = this.t('jitsi', 'It is highly recommended to set up a dedicated Jitsi instance')
        }

        this.jwtAppIdMessage = ''

        if (this.jwtSecret && !this.jwtAppId) {
            this.jwtAppIdMessage = this.t('jitsi', 'Please provide the App ID')
        }
    },
    
    async updateSetting(name, value) {
        try {
            const response = await axios.post(
                generateUrl('/apps/jitsi/api/admin/settings'),
                { key: name, value: String(value) }
            )
            return response.data
        } catch (e) {
            console.error(`Failed to save setting ${name}:`, e)
            this.errorMessage = this.t('jitsi', 'Failed to save settings')
            throw e
        }
    },
    
    async loadSetting(name, defaultValue = null) {
        try {
            const response = await axios.get(
                generateUrl(`/apps/jitsi/api/admin/settings/${name}`),
                { params: { default: defaultValue } }
            )
            return response.data.value
        } catch (e) {
            // If setting doesn't exist, return default
            if (e.response?.status === 404) {
                return defaultValue
            }
            console.error(`Failed to load setting ${name}:`, e)
            this.errorMessage = this.t('jitsi', 'Failed to load settings')
            throw e
        }
    },
},
```

**Testing**:
```javascript
// In browser console, test:
axios.post(generateUrl('/apps/jitsi/api/admin/settings'), 
    { key: 'test_key', value: 'test_value' }
).then(r => console.log(r.data))

axios.get(generateUrl('/apps/jitsi/api/admin/settings/test_key'))
    .then(r => console.log(r.data))
```

#### SOLUTION OPTION B: Use Direct HTTP Call (Simpler)

If you don't want to create a new controller, you can use the existing Nextcloud OCP API via HTTP:

**File**: `src/Admin.vue` (ALTERNATIVE)

```javascript
// Add to imports
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// Replace methods.updateSetting()
async updateSetting(name, value) {
    try {
        // Make direct OCS API call
        const response = await axios.post(
            generateUrl('/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/jitsi/' + name),
            { value: String(value) },
            {
                headers: {
                    'OCS-APIREQUEST': 'true'
                }
            }
        )
        return response.data
    } catch (e) {
        console.error(`Failed to save setting ${name}:`, e)
        this.errorMessage = this.t('jitsi', 'Failed to save settings')
        throw e
    }
}

// Replace methods.loadSetting()
async loadSetting(name, defaultValue = null) {
    try {
        const response = await axios.get(
            generateUrl('/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/jitsi/' + name),
            {
                headers: {
                    'OCS-APIREQUEST': 'true'
                }
            }
        )
        // Extract value from OCS response
        const status = response.data.ocs.meta.status
        if (status === 'ok') {
            return response.data.ocs.data.value
        }
        return defaultValue
    } catch (e) {
        if (e.response?.status === 404) {
            return defaultValue
        }
        console.error(`Failed to load setting ${name}:`, e)
        this.errorMessage = this.t('jitsi', 'Failed to load settings')
        throw e
    }
}
```

**Recommendation**: Use Option A (Custom API endpoint) as it's cleaner and more maintainable.

---

## HIGH PRIORITY ISSUES

### Issue #3: Replace Vue 2 Event Bus Pattern

**Files**:
- `src/Room.vue` (Lines 284-294, 494-504, 521)
- `src/components/BrowserTest.vue` (Line 101)

#### Solution: Use mitt Event Bus Library

**Step 1**: Install mitt

```bash
npm install mitt
```

**Step 2**: Create event bus file

**File**: `src/utils/eventBus.js` (NEW FILE)

```javascript
import mitt from 'mitt'

export const bus = mitt()
```

**Step 3**: Update Room.vue

```javascript
// Add to imports
import { bus } from '../utils/eventBus'

export default {
    name: 'Room',
    // ... existing component options ...
    
    async created() {
        // Replace this.$root.$on with bus.on
        bus.on('jitsi.device_permission_denied', () => {
            this.permissionDenied = true
        })

        bus.on('jitsi.system_test_done', () => {
            this.systemTestDone = true
        })

        bus.on('tol-browser-status', (status) => {
            this.browserStatus = status
        })

        // ... rest of created() ...
    },
    
    // ADD THIS (was missing!)
    beforeDestroy() {
        // Clean up event listeners to prevent memory leaks
        bus.off('jitsi.device_permission_denied')
        bus.off('jitsi.system_test_done')
        bus.off('tol-browser-status')
        bus.off('mic-stopped')
        bus.off('cam-stopped')
    },
    
    methods: {
        // ... existing methods ...
        
        async stopStreams() {
            return new Promise((resolve) => {
                let micStopped = false
                let camStopped = false

                const micHandler = () => {
                    micStopped = true
                    if (camStopped) {
                        resolve()
                    }
                }

                const camHandler = () => {
                    camStopped = true
                    if (micStopped) {
                        resolve()
                    }
                }

                // Replace once with on (mitt doesn't have once)
                bus.on('mic-stopped', micHandler)
                bus.on('cam-stopped', camHandler)

                bus.emit('stop-streams')
                
                // Clean up listeners after callback
                setTimeout(() => {
                    bus.off('mic-stopped', micHandler)
                    bus.off('cam-stopped', camHandler)
                }, 60000) // Timeout after 60s
            })
        },
        
        // ... rest of methods ...
    }
}
```

**Step 4**: Update BrowserTest.vue

```javascript
// Add to imports
import { bus } from '../../utils/eventBus'

export default {
    // ... component options ...
    
    async created() {
        // ... existing code ...
        
        // Replace this.$root.$emit with bus.emit
        if (this.isOptimalBrowser) {
            this.status = 'ok'
            bus.emit('tol-browser-status', 'ok')
            return
        }

        if (this.isNotWorkingBrowser) {
            this.status = 'error'
            bus.emit('tol-browser-status', 'error')
            return
        }

        this.status = 'warning'
        bus.emit('tol-browser-status', 'warning')
    }
}
```

**Step 5**: Update SystemTest.vue to emit events with bus

```javascript
// In SystemTest.vue methods
async created() {
    // ... existing code ...
    bus.emit('jitsi.device_permission_denied')
    bus.emit('jitsi.system_test_done')
}
```

---

### Issue #4: Add Event Listener Cleanup

**File**: `src/Room.vue`  
**Already Covered Above**: Added `beforeDestroy()` hook in Issue #3 solution

---

### Issue #5: Verify FeaturePolicy & ContentSecurityPolicy APIs

**File**: `lib/Controller/PageController.php`  
**Lines**: 8-9, 83-94

The API looks like it should work, but needs verification:

```bash
# Check if methods exist in your NC33 installation
grep -r "setFeaturePolicy\|setContentSecurityPolicy" /path/to/nc33/lib/AppFramework/Http/

# Test the policy setting
# 1. Enable Jitsi app
# 2. Open browser dev console -> Network tab
# 3. Check response headers for Feature-Policy and Content-Security-Policy
# 4. Verify values match what you set in code
```

If API methods don't exist, use Response headers directly:

```php
// Alternative approach using response headers
private function setPolicies(Response $response): void {
    $serverUrl = $this->appConfig->jitsiServerUrl();
    $serverHost = $this->determineJitsiHost();

    if ($serverUrl === null || $serverHost === null) {
        return;
    }

    // Set CSP header
    $cspHeader = "frame-src 'self' https://$serverHost";
    $response->addHeader('Content-Security-Policy', $cspHeader);

    // Set Feature Policy header (deprecated, use Permissions-Policy instead)
    $fpHeader = "camera 'self' $serverUrl; microphone 'self' $serverUrl";
    $response->addHeader('Permissions-Policy', $fpHeader);
}
```

---

### Issue #6: Verify Avatar Route

**File**: `lib/Controller/UserController.php`  
**Line**: 67

Create a test controller method:

```php
// Add this method temporarily to test
public function testAvatarRoute(): DataResponse {
    try {
        $url = $this->urlGenerator->linkToRouteAbsolute('core.avatar.getAvatar', [
            'userId' => 'admin',
            'size' => 256,
        ]);
        
        return new DataResponse([
            'success' => true,
            'url' => $url
        ]);
    } catch (\Exception $e) {
        return new DataResponse([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}
```

Test endpoint and verify URL format is correct.

---

### Issue #7: Verify AdminSettings Interface

**File**: `lib/Settings/AdminSettings.php`

Verify the interface in NC33:

```bash
# Find the interface definition
grep -r "interface ISettings" /path/to/nc33/lib/Settings/

# Check method signatures
grep -A 20 "interface ISettings" /path/to/nc33/lib/Settings/ISettings.php
```

If interface changed, update implementation accordingly.

---

## MEDIUM PRIORITY ISSUES

### Issue #8: Migrate localStorage to Per-User AppConfig

**File**: `src/Room.vue`  
**Lines**: 290-291, 300-301, 516-517, 521

#### Create PHP Endpoint for User Settings

**File**: `lib/Controller/UserController.php` (UPDATE EXISTING)

```php
<?php

declare(strict_types=1);

namespace OCA\jitsi\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IConfig;
use OCA\jitsi\AppInfo\Application;

class UserController extends Controller {
    // ... existing methods ...
    
    /**
     * Get user setting value
     * 
     * @NoAdminRequired
     * @param string $key Setting key
     * @return DataResponse
     */
    public function getUserSetting(string $key): DataResponse {
        $user = $this->userSession->getUser();
        
        if ($user === null) {
            return new DataResponse([], Http::STATUS_UNAUTHORIZED);
        }
        
        $value = $this->config->getUserValue(
            $user->getUID(),
            Application::APP_ID,
            $key,
            ''
        );
        
        return new DataResponse([
            'key' => $key,
            'value' => $value
        ]);
    }
    
    /**
     * Set user setting value
     * 
     * @NoAdminRequired
     * @param string $key Setting key
     * @param string $value Setting value
     * @return DataResponse
     */
    public function setUserSetting(string $key, string $value): DataResponse {
        $user = $this->userSession->getUser();
        
        if ($user === null) {
            return new DataResponse([], Http::STATUS_UNAUTHORIZED);
        }
        
        $this->config->setUserValue(
            $user->getUID(),
            Application::APP_ID,
            $key,
            $value
        );
        
        return new DataResponse([
            'key' => $key,
            'value' => $value
        ]);
    }
}
```

**File**: `appinfo/routes.php` (UPDATE)

```php
return [
    'routes' => [
        // ... existing routes ...
        
        // User settings API
        ['name' => 'user#getUserSetting', 'url' => '/api/user/settings/{key}', 'verb' => 'GET'],
        ['name' => 'user#setUserSetting', 'url' => '/api/user/settings', 'verb' => 'POST'],
    ],
];
```

#### Update Room.vue to Use HTTP API

**File**: `src/Room.vue` (UPDATE created() hook)

```javascript
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// In methods:
async loadUserSetting(key, defaultValue = '') {
    try {
        const response = await axios.get(
            generateUrl(`/apps/jitsi/api/user/settings/${key}`)
        )
        return response.data.value || defaultValue
    } catch (e) {
        if (e.response?.status === 404) {
            return defaultValue
        }
        console.error(`Failed to load user setting ${key}:`, e)
        return defaultValue
    }
}

async saveUserSetting(key, value) {
    try {
        await axios.post(
            generateUrl('/apps/jitsi/api/user/settings'),
            { key, value: String(value) }
        )
    } catch (e) {
        console.error(`Failed to save user setting ${key}:`, e)
    }
}

// Update async created():
async created() {
    // Load settings from server instead of localStorage
    this.startMuted = (await this.loadUserSetting('startMuted', 'false')) === 'true'
    this.startCameraOff = (await this.loadUserSetting('startCameraOff', 'false')) === 'true'
    this.userName = await this.loadUserSetting('userName', '')
    
    // ... rest of created() ...
}

// Update computed property setters:
startMuted: {
    get() {
        return this._startMuted
    },
    set(startMuted) {
        this._startMuted = startMuted
        this.saveUserSetting('startMuted', String(startMuted)) // Save to server
        localStorage.setItem('jitsi.startMuted', startMuted) // Fallback for offline
    },
},

startCameraOff: {
    get() {
        return this._startCameraOff
    },
    set(startCameraOff) {
        this._startCameraOff = startCameraOff
        this.saveUserSetting('startCameraOff', String(startCameraOff)) // Save to server
        localStorage.setItem('jitsi.startCameraOff', startCameraOff) // Fallback for offline
    },
},

// Update joinBrowser() method:
async joinBrowser() {
    if (this.joining) {
        return
    }

    // ... existing code ...

    if (!this.user && this.userName) {
        await this.saveUserSetting('userName', this.userName)
        localStorage.setItem('jitsi.userName', this.userName) // Fallback
    }

    // ... rest of method ...
}
```

---

### Issue #9: Verify QueryBuilder API

**File**: `lib/Db/RoomMapper.php`

Create a test script:

```bash
# In Nextcloud root, run:
php -r "
\$db = \OC::query(\OCP\IDBConnection::class);
\$qb = \$db->getQueryBuilder();
echo 'getQueryBuilder: OK' . PHP_EOL;
echo 'expr available: ' . (method_exists(\$qb->expr(), 'eq') ? 'OK' : 'FAIL') . PHP_EOL;
echo 'iLike available: ' . (method_exists(\$qb->expr(), 'iLike') ? 'OK' : 'FAIL') . PHP_EOL;
echo 'createNamedParameter: ' . (method_exists(\$qb, 'createNamedParameter') ? 'OK' : 'FAIL') . PHP_EOL;
"
```

---

### Issue #10: Plan Navigation Registration Migration

**Current**: `appinfo/info.xml`  
**Future**: Use `registerNavigationEntry()` in `Application.php`

**For Now**: Keep XML format, it still works.

**For Future (NC34+)**: Implement this pattern:

```php
// lib/Navigation/JitsiNavigationEntry.php
use OCP\Navigation\INavigationEntry;
use OCP\Navigation\NavigationEntry;

public function __construct(
    private IAppManager $appManager,
    private IURLGenerator $urlGenerator,
) {}

public function getNavigationEntry(): INavigationEntry {
    return new NavigationEntry([
        'id' => 'jitsi',
        'order' => 20,
        'href' => $this->urlGenerator->linkToRoute('jitsi.page.index'),
        'icon' => $this->urlGenerator->imagePath('jitsi', 'app-dark.svg'),
        'name' => 'Conferences',
        'type' => 'link',
    ]);
}
```

---

## TESTING GUIDE

### Pre-Deployment Checklist

```bash
# 1. Syntax check
php -l lib/**/*.php
npm run lint src/

# 2. Local testing (NC32/NC33 test instance)
## Admin Panel
- Navigate to Settings > Administration > Jitsi
- Verify form loads
- Try saving settings
- Verify settings persist after page reload

## Main App
- Navigate to /apps/jitsi
- Create new room
- Try to join room
- Verify system test works
- Check browser console for errors

## Event Bus
- Open browser console
- Create room and join
- Verify no errors in console
- Check for memory leaks (DevTools Memory tab)

## localStorage → AppConfig migration
- Enable settings save to AppConfig
- Clear localStorage
- Verify settings still load from server

## APIs
- Check HTTP response headers for CSP/FP
- Test avatar URL generation
- Verify all AJAX calls work

# 3. Browser testing
- Chrome/Chromium (latest)
- Firefox (latest)
- Safari (if Mac)
- Edge (if Windows)

# 4. Accessibility testing
- Tab through form
- Test with screen reader
- Verify form labels
```

### Debug Commands

```javascript
// Check event bus
window.bus  // Should show mitt instance
window.bus.all  // Should show registered events

// Check settings API
axios.get('/apps/jitsi/api/admin/settings/jitsi_server_url')
axios.post('/apps/jitsi/api/admin/settings', {
    key: 'jitsi_server_url',
    value: 'https://meet.example.com/'
})

// Check user settings API
axios.get('/apps/jitsi/api/user/settings/userName')
axios.post('/apps/jitsi/api/user/settings', {
    key: 'startMuted',
    value: 'true'
})

// Check avatar URL
axios.get('/apps/jitsi/api/user')  // Should return avatar URL

// Monitor network requests
// DevTools > Network tab > Filter by 'jitsi'
```

---

## Summary

| Phase | Timeline | Items |
|-------|----------|-------|
| Phase 1 | Before NC32 install | Update version constraint, replace OCP.AppConfig |
| Phase 2 | Before production | Event bus, event cleanup, verify APIs |
| Phase 3 | 1-2 releases | localStorage → AppConfig, component updates |
| Phase 4 | 6+ months | Vue 3 migration, modern patterns |

---

**Next Step**: Implement and test Phase 1 items immediately.
