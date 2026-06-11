# Nextcloud Jitsi App - Nextcloud 33 Migration Plan

**Date**: June 11, 2026  
**App Version**: 0.19.0  
**Current NC Support**: 25-31 (OUTDATED)  
**Target**: NC32 + NC33 dual support from single codebase  

---

## Executive Summary

The Nextcloud Jitsi Integration app (v0.19.0) requires a migration to support Nextcloud 32 and 33 while maintaining backward compatibility with NC32. The audit identified **15 compatibility concerns** across the codebase:

- **2 CRITICAL** issues blocking NC32/33 support
- **6 HIGH** priority issues requiring immediate fixes
- **5 MEDIUM** priority issues needing verification/updates
- **3 LOW** priority improvements

**Recommendation**: **Option A - Single Codebase with Compatibility Layer** is viable and preferred over forking. The app can support NC32 and NC33 from a single branch using runtime feature detection and a compatibility abstraction layer.

**Estimated Effort**: 40-60 developer hours  
**Risk Level**: Medium  
**Breaking Changes**: None if handled correctly

---

## Phase 1: Complete Incompatibility List

### CRITICAL ISSUES (Must Fix Before Release)

#### Issue #1: App Version Constraint Blocks Installation

| Property | Value |
|----------|-------|
| **File** | [appinfo/info.xml](appinfo/info.xml#L27) |
| **Line** | 27 |
| **Severity** | 🔴 CRITICAL |
| **Component** | App Metadata |
| **Current** | `<nextcloud min-version="25" max-version="31"/>` |
| **Problem** | Explicitly blocks installation on NC32 and NC33 |
| **Impact** | App cannot be installed on NC32/NC33 instances |
| **Required Action** | Update to `<nextcloud min-version="25" max-version="33"/>` |
| **NC32 Compatible** | ✅ Yes |
| **NC33 Compatible** | ✅ Yes |
| **Migration Effort** | Trivial (1 line change) |

---

#### Issue #2: Deprecated JavaScript Settings API (OCP.AppConfig)

| Property | Value |
|----------|-------|
| **File** | [src/Admin.vue](src/Admin.vue#L244-L268) |
| **Lines** | 244-268 |
| **Severity** | 🔴 CRITICAL |
| **Component** | Frontend Settings |
| **APIs Affected** | `OCP.AppConfig.setValue()`, `OCP.AppConfig.getValue()` |
| **Problem** | Legacy XML-based API removed/deprecated in NC32/NC33 |
| **Impact** | Admin settings page fails to load/save settings |
| **Required Action** | Replace with HTTP-based REST API using axios |
| **NC32 Compatible** | ⚠️ Needs HTTP fallback |
| **NC33 Compatible** | ✅ Yes (HTTP API) |
| **Migration Effort** | High (80-120 minutes) |

**Current Code**:
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

**Replacement Strategy**: Create PHP REST API endpoint → Call with axios from Vue

---

### HIGH PRIORITY ISSUES

#### Issue #3: Vue 2 Event Bus Pattern ($root event bus)

| Property | Value |
|----------|-------|
| **Files** | [src/Room.vue](src/Room.vue#L284-L294,494-504,521), [src/components/BrowserTest.vue](src/components/BrowserTest.vue#L101) |
| **Lines** | Room.vue: 284-294, 494-504, 521 / BrowserTest.vue: 101 |
| **Severity** | 🟠 HIGH |
| **Component** | Frontend Event Bus |
| **APIs Affected** | `this.$root.$on()`, `this.$root.$emit()`, `this.$root.$once()` |
| **Problem** | Vue 2 pattern incompatible with Vue 3 upgrade path |
| **Impact** | Device selection, browser status, and stream management fail |
| **Required Action** | Replace with mitt library (works with Vue 2 and 3) |
| **NC32 Compatible** | ✅ Yes |
| **NC33 Compatible** | ✅ Yes |
| **Migration Effort** | Medium (60-90 minutes) |

**Current Code in Room.vue**:
```javascript
// Line 284-294: Listening for device events
this.$root.$on('jitsi.device_permission_denied', () => {
    this.permissionDenied = true
})

this.$root.$on('jitsi.system_test_done', () => {
    this.systemTestDone = true
})

this.$root.$on('tol-browser-status', (status) => {
    this.browserStatus = status
})

// Lines 494-504: Stopping streams with event coordination
async stopStreams() {
    return new Promise((resolve) => {
        let micStopped = false
        let camStopped = false

        this.$root.$once('mic-stopped', () => {
            micStopped = true
            if (camStopped) {
                resolve()
            }
        })

        this.$root.$once('cam-stopped', () => {
            camStopped = true
            if (micStopped) {
                resolve()
            }
        })

        this.$root.$emit('stop-streams')
    })
}

// Line 521: Emitting resume event
api.addEventListener('readyToClose', () => {
    // ...
    this.$root.$emit('resume-preview')
})
```

**Replacement Strategy**: Use mitt library with global event bus instance

**Issue**: Missing event listener cleanup in `beforeDestroy()` causes memory leaks (all $root.$on listeners are never cleaned up)

---

#### Issue #4: Missing Event Listener Cleanup

| Property | Value |
|----------|-------|
| **File** | [src/Room.vue](src/Room.vue#L278-L294) |
| **Lines** | 278-294 (created lifecycle) |
| **Severity** | 🟠 HIGH |
| **Component** | Frontend Lifecycle |
| **Problem** | Event listeners registered but never removed |
| **Impact** | Memory leaks when component is destroyed |
| **Required Action** | Add `beforeDestroy()` hook to unregister all listeners |
| **NC32 Compatible** | ✅ Yes |
| **NC33 Compatible** | ✅ Yes (will auto-cleanup with mitt) |
| **Migration Effort** | Trivial (5 minutes) |

---

#### Issue #5: BrowserTest.vue Event Bus Usage

| Property | Value |
|----------|-------|
| **File** | [src/components/BrowserTest.vue](src/components/BrowserTest.vue#L101) |
| **Line** | 101 |
| **Severity** | 🟠 HIGH |
| **Component** | Frontend Component |
| **Current Code** | `this.$root.$emit('tol-browser-status', this.status)` |
| **Problem** | Vue 2 event bus pattern |
| **Impact** | Browser status detection fails for Room.vue listener |
| **Required Action** | Replace with mitt global event bus |
| **NC32 Compatible** | ✅ Yes |
| **NC33 Compatible** | ✅ Yes |
| **Migration Effort** | Trivial (5 minutes) |

---

#### Issue #6: ContentSecurityPolicy and FeaturePolicy APIs

| Property | Value |
|----------|-------|
| **File** | [lib/Controller/PageController.php](lib/Controller/PageController.php#L83-94) |
| **Lines** | 83-94 |
| **Severity** | 🟠 HIGH |
| **Component** | Backend HTTP Policies |
| **APIs Used** | `ContentSecurityPolicy`, `FeaturePolicy` |
| **Problem** | APIs may have changed or been removed in NC33 |
| **Impact** | Security policies may not be applied correctly |
| **Required Action** | Verify against NC33 documentation; test in environment |
| **NC32 Compatible** | Likely ✅ |
| **NC33 Compatible** | ⚠️ Requires verification |
| **Migration Effort** | Low-Medium (30-45 minutes testing) |

**Current Code**:
```php
private function setPolicies(Response $response): void {
    $serverUrl = $this->appConfig->jitsiServerUrl();
    $serverHost = $this->determineJitsiHost();

    if ($serverUrl === null || $serverHost === null) {
        return;
    }

    $csp = new ContentSecurityPolicy();
    $csp->addAllowedFrameDomain($serverHost);
    $response->setContentSecurityPolicy($csp);

    $fp = new FeaturePolicy();
    $fp->addAllowedCameraDomain('https://nextcloud.local');
    $fp->addAllowedCameraDomain('https://' . $_SERVER['HTTP_HOST']);
    $fp->addAllowedCameraDomain($serverUrl);
    $fp->addAllowedMicrophoneDomain('https://nextcloud.local');
    $fp->addAllowedMicrophoneDomain('https://' . $_SERVER['HTTP_HOST']);
    $fp->addAllowedMicrophoneDomain($serverUrl);
    $response->setFeaturePolicy($fp);
}
```

**Verification Steps**:
1. Check if classes still exist in NC33 OCP namespace
2. Verify method signatures haven't changed
3. Test frame loading and microphone/camera access

---

#### Issue #7: Avatar Route Compatibility

| Property | Value |
|----------|-------|
| **File** | [lib/Controller/UserController.php](lib/Controller/UserController.php#L67) |
| **Line** | 67 |
| **Severity** | 🟠 HIGH |
| **Component** | Backend User Data |
| **Route Used** | `core.avatar.getAvatar` |
| **Problem** | Route structure may have changed in NC33 |
| **Impact** | User avatars not loaded in Jitsi iframe |
| **Required Action** | Verify route exists and parameters unchanged |
| **NC32 Compatible** | Likely ✅ |
| **NC33 Compatible** | ⚠️ Requires verification |
| **Migration Effort** | Low (15-30 minutes testing) |

**Current Code**:
```php
private function loadAvatarUrl(): ?string {
    if (!$this->userSession->isLoggedIn()) {
        return null;
    }

    $userId = $this->userSession->getUser()->getUID();

    try {
        return \OC::$server->getURLGenerator()->linkToRouteAbsolute(
            'core.avatar.getAvatar',
            [
                'userId' => $userId,
                'size' => 128,
            ]
        );
    } catch (\Exception $e) {
        return null;
    }
}
```

**Verification Steps**:
1. Check if `core.avatar.getAvatar` route still exists
2. Verify parameter names (`userId`, `size`)
3. Test avatar loading in Jitsi room

---

#### Issue #8: AdminSettings Interface Compatibility

| Property | Value |
|----------|-------|
| **File** | [lib/Settings/AdminSettings.php](lib/Settings/AdminSettings.php) |
| **Lines** | All |
| **Severity** | 🟠 HIGH |
| **Component** | Backend Settings Integration |
| **Interface** | `OCP\Settings\ISettings` |
| **Problem** | Interface methods may have changed in NC33 |
| **Impact** | Admin settings page may not render |
| **Required Action** | Verify interface compliance in NC33 |
| **NC32 Compatible** | Likely ✅ |
| **NC33 Compatible** | ⚠️ Requires verification |
| **Migration Effort** | Low (30-45 minutes testing) |

**Current Code**:
```php
class AdminSettings implements ISettings {
    public function getForm() {
        return new TemplateResponse('jitsi', 'admin', []);
    }

    public function getSection() {
        return 'jitsi';
    }

    public function getPriority() {
        return 50;
    }
}
```

**Verification Steps**:
1. Compare ISettings interface in NC32 vs NC33 docs
2. Check if any new methods are required
3. Test admin settings page loads and renders

---

### MEDIUM PRIORITY ISSUES

#### Issue #9: localStorage User Preferences

| Property | Value |
|----------|-------|
| **File** | [src/Room.vue](src/Room.vue#L290-291,300-301,516-517,521) |
| **Lines** | 290-291, 300-301, 516-517, 521 |
| **Severity** | 🟡 MEDIUM |
| **Component** | Frontend User Preferences |
| **Problem** | localStorage not synced across devices/browsers |
| **Impact** | User preferences (start muted, camera off) lost on different devices |
| **Current Implementation** | localStorage direct access |
| **Required Action** | Migrate to per-user AppConfig API (server-side) |
| **NC32 Compatible** | ✅ Yes |
| **NC33 Compatible** | ✅ Yes |
| **Migration Effort** | Medium (45-60 minutes) |

**Current Code**:
```javascript
startMuted: {
    get() {
        return this._startMuted
    },
    set(startMuted) {
        this._startMuted = startMuted
        localStorage.setItem('jitsi.startMuted', startMuted)
    },
},
startCameraOff: {
    get() {
        return this._startCameraOff
    },
    set(startCameraOff) {
        this._startCameraOff = startCameraOff
        localStorage.setItem('jitsi.startCameraOff', startCameraOff)
    },
},

// Usage:
this.startMuted = localStorage.getItem('jitsi.startMuted') === 'true'
this.startCameraOff = localStorage.getItem('jitsi.startCameraOff') === 'true'

// And when saving username:
if (!this.user && this.userName) {
    localStorage.setItem('jitsi.userName', this.userName)
}
this.userName = localStorage.getItem('jitsi.userName')
```

**Solution**: Create a UserPreferences PHP API endpoint that stores preferences in `oc_appconfig` table per user

---

#### Issue #10: QueryBuilder API Compatibility

| Property | Value |
|----------|-------|
| **File** | [lib/Db/RoomMapper.php](lib/Db/RoomMapper.php#L25,39,57,88) |
| **Lines** | 25, 39, 57, 88 |
| **Severity** | 🟡 MEDIUM |
| **Component** | Backend Database |
| **APIs Used** | `getQueryBuilder()`, `expr()->iLike()` |
| **Problem** | QueryBuilder API may have changed in NC33 |
| **Impact** | Room search/listing may fail |
| **Required Action** | Verify QueryBuilder API unchanged; test queries |
| **NC32 Compatible** | Likely ✅ |
| **NC33 Compatible** | ⚠️ Requires verification |
| **Migration Effort** | Low (30 minutes testing) |

---

#### Issue #11: Legacy Navigation Registration

| Property | Value |
|----------|-------|
| **File** | [appinfo/info.xml](appinfo/info.xml#L37-41) |
| **Lines** | 37-41 |
| **Severity** | 🟡 MEDIUM |
| **Component** | App Navigation |
| **Current Pattern** | XML-based navigation in info.xml |
| **Problem** | Legacy pattern; newer apps use `registerNavigationEntry()` |
| **Impact** | Navigation may not appear in NC33 sidebar |
| **Current Code** | `<navigations><navigation>...` XML format |
| **Required Action** | Verify format still works; plan migration to registerNavigationEntry() |
| **NC32 Compatible** | ✅ Likely works |
| **NC33 Compatible** | ⚠️ Needs verification, may be deprecated |
| **Migration Effort** | Medium (60 minutes for proper migration) |

---

#### Issue #12: Custom CSS Instead of @nextcloud/vue Components

| Property | Value |
|----------|-------|
| **File** | [src/Admin.vue](src/Admin.vue) - Multiple custom styles |
| **Severity** | 🟡 MEDIUM |
| **Component** | Frontend UI |
| **Problem** | Custom CSS may not align with NC33 design system |
| **Impact** | Settings page appearance may look inconsistent |
| **Required Action** | Replace with @nextcloud/vue components (NcButton, NcInputField, etc.) |
| **NC32 Compatible** | ✅ Yes |
| **NC33 Compatible** | ✅ Yes |
| **Migration Effort** | Medium (60-90 minutes) |

---

#### Issue #13: AppConfig API Usage Pattern

| Property | Value |
|----------|-------|
| **File** | [lib/Config/Config.php](lib/Config/Config.php) - All methods |
| **Severity** | 🟡 MEDIUM |
| **Component** | Backend Configuration |
| **APIs Used** | `IConfig::getAppValue()`, `IConfig::setAppValue()` |
| **Problem** | Core OCP\IConfig API may have minor changes in NC33 |
| **Impact** | Settings may not load/save properly |
| **Required Action** | Verify these methods exist in NC33 OCP API |
| **NC32 Compatible** | ✅ Yes |
| **NC33 Compatible** | ⚠️ Likely yes, needs verification |
| **Migration Effort** | Low (20 minutes) |

---

### LOW PRIORITY ISSUES

#### Issue #14: SettingsSection Component API

| Property | Value |
|----------|-------|
| **File** | [src/Admin.vue](src/Admin.vue#L5,146) |
| **Lines** | 5 (import), 146 (usage) |
| **Severity** | 🟢 LOW |
| **Component** | Frontend Components |
| **Component Used** | `SettingsSection` from `@nextcloud/vue` |
| **Problem** | Component props/slots may have changed |
| **Impact** | Settings form layout may look incorrect |
| **Required Action** | Verify component API in @nextcloud/vue v5+ |
| **NC32 Compatible** | ✅ Yes |
| **NC33 Compatible** | ✅ Likely yes |
| **Migration Effort** | Trivial (10 minutes verification) |

---

#### Issue #15: External Jitsi API Stability

| Property | Value |
|----------|-------|
| **File** | [src/Room.vue](src/Room.vue#L176) |
| **Line** | 176 |
| **Severity** | 🟢 LOW |
| **Component** | Frontend External API |
| **API Used** | JitsiMeetExternalAPI (loaded via script tag) |
| **Problem** | External API may have breaking changes |
| **Impact** | Jitsi room functionality fails |
| **Required Action** | Monitor Jitsi upstream releases; test with target Jitsi version |
| **NC32 Compatible** | ✅ Yes |
| **NC33 Compatible** | ✅ Yes |
| **Migration Effort** | None (dependency on upstream) |

---

## Phase 2: Nextcloud 32 vs 33 Compatibility Matrix

| Component | Current Implementation | NC32 Status | NC33 Status | Required Action |
|-----------|------------------------|-------------|-------------|-----------------|
| **App Version Constraint** | max-version="31" | 🔴 Blocks | 🔴 Blocks | Update to max-version="33" |
| **Settings API (JS)** | OCP.AppConfig XML API | ⚠️ Works | 🔴 Removed | Create HTTP REST endpoint |
| **Settings API (PHP)** | IConfig::getAppValue() | ✅ Works | ✅ Expected | Verify in NC33 |
| **Event Bus** | Vue 2 $root pattern | ✅ Works | ⚠️ Deprecated | Replace with mitt |
| **CSP/FeaturePolicy** | OCP HTTP classes | ✅ Works | ⚠️ Check | Verify API compatibility |
| **Avatar Route** | core.avatar.getAvatar | ✅ Works | ⚠️ Check | Verify route exists |
| **Settings Interface** | OCP\Settings\ISettings | ✅ Works | ⚠️ Check | Verify interface unchanged |
| **QueryBuilder** | OCP DB QueryBuilder | ✅ Works | ⚠️ Check | Verify API unchanged |
| **Navigation** | XML in info.xml | ✅ Works | ⚠️ Legacy | Verify; plan migration |
| **AppBootstrap** | IBootstrap interface | ✅ Works | ✅ Expected | No changes needed |
| **Search Provider** | OCP\Search\IProvider | ✅ Works | ✅ Expected | No changes needed |
| **Admin Section** | OCP\Settings\IAdminSection | ✅ Works | ✅ Expected | No changes needed |
| **Database Migrations** | SimpleMigrationStep | ✅ Works | ✅ Expected | No changes needed |
| **HTTP Responses** | DataResponse, TemplateResponse | ✅ Works | ✅ Expected | No changes needed |

---

## Phase 3: Migration Strategy Comparison

### Option A: Single Codebase with Compatibility Layer (RECOMMENDED)

#### Advantages
✅ No branch maintenance overhead  
✅ Easier for contributors (single codebase)  
✅ Simpler release process  
✅ Automatic feature parity  
✅ Easier for users (one package)  

#### Disadvantages
⚠️ Some code duplication (version checks)  
⚠️ Slightly larger codebase  
⚠️ Need comprehensive testing on both versions  

#### Implementation Strategy

```javascript
// Create src/utils/compat.js - Compatibility layer
export const ncVersion = Math.floor(parseFloat(window.OC?.config?.version || '32'))

export const usesLegacyAppConfig = () => ncVersion < 32

export const usesHttpSettingsAPI = () => ncVersion >= 32
```

```php
// Create lib/Compat/VersionHelper.php - Backend version detection
namespace OCA\jitsi\Compat;

use OCP\Util;

class VersionHelper {
    public static function isNextcloud33Plus(): bool {
        $version = Util::getVersion();
        return $version[0] >= 33;
    }

    public static function isNextcloud32Plus(): bool {
        $version = Util::getVersion();
        return $version[0] >= 32;
    }
}
```

#### Conditional Implementation Patterns

**For Frontend (Vue)**:
```javascript
// In Admin.vue or util function
import { usesHttpSettingsAPI } from '@/utils/compat'

async updateSetting(name, value) {
    if (usesHttpSettingsAPI()) {
        return this.updateSettingViaHttp(name, value)
    } else {
        return this.updateSettingViaLegacyAPI(name, value)
    }
}
```

**For Backend (PHP)**:
```php
use OCA\jitsi\Compat\VersionHelper;

if (VersionHelper::isNextcloud32Plus()) {
    // Use new API
} else {
    // Use legacy API or fallback
}
```

---

### Option B: Separate Compatibility Layer

#### Advantages
✅ Cleaner separation of concerns  
✅ Easier to test each version path independently  

#### Disadvantages
❌ More code duplication  
❌ Harder to maintain feature parity  
❌ More complex release process  
❌ Larger final package  

#### Not Recommended for this app

---

## Phase 4: Detailed Code Modifications

### 4.1 CRITICAL FIX #1: Update info.xml Version Constraint

**File**: `appinfo/info.xml`

**Change**:
```xml
<!-- BEFORE -->
<dependencies>
    <nextcloud min-version="25" max-version="31"/>
</dependencies>

<!-- AFTER -->
<dependencies>
    <nextcloud min-version="25" max-version="33"/>
</dependencies>
```

**Testing**:
- Install on NC32 instance → should succeed
- Install on NC33 instance → should succeed

---

### 4.2 CRITICAL FIX #2: Replace OCP.AppConfig with HTTP Settings API

#### Step 1: Create Backend AdminController

**File**: `lib/Controller/AdminController.php` (NEW)

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
     * @AdminRequired
     * @NoCSRFRequired
     * Get a setting value
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
     * Set a setting value
     */
    public function setSetting(string $key, string $value): DataResponse {
        $this->config->setAppValue(
            Application::APP_ID,
            $key,
            $value
        );
        
        return new DataResponse([
            'key' => $key,
            'value' => $value,
            'success' => true
        ]);
    }
}
```

#### Step 2: Register Route

**File**: `appinfo/routes.php`

```php
<?php

return [
    'routes' => [
        // ... existing routes ...
        
        // Settings API endpoints
        ['name' => 'admin#getSetting', 'url' => '/api/settings/{key}', 'verb' => 'GET'],
        ['name' => 'admin#setSetting', 'url' => '/api/settings/{key}', 'verb' => 'PUT'],
    ],
];
```

#### Step 3: Update Admin.vue to Use HTTP API

**File**: `src/Admin.vue`

Replace the `updateSetting()` and `loadSetting()` methods:

```javascript
methods: {
    // ... existing methods ...

    async updateSetting(name, value) {
        try {
            const url = generateUrl(`/apps/jitsi/api/settings/${name}`)
            const response = await axios.put(url, { value })
            
            if (!response.data.success) {
                this.errorMessage = this.t('jitsi', 'Failed to save settings')
                throw new Error('Settings update failed')
            }
            
            return response.data
        } catch (e) {
            this.errorMessage = this.t('jitsi', 'Failed to save settings')
            console.error('Failed to save setting:', e)
            throw e
        }
    },

    async loadSetting(name, defaultValue = null) {
        try {
            const url = generateUrl(`/apps/jitsi/api/settings/${name}`)
            const response = await axios.get(url, {
                params: { default: defaultValue }
            })
            
            if (!response.data.success) {
                this.errorMessage = this.t('jitsi', 'Failed to load settings')
                console.error('Failed to load setting:', response.data)
                return defaultValue
            }
            
            return response.data.value || defaultValue
        } catch (e) {
            if (e?.response?.status === 404) {
                // Setting doesn't exist, return default
                return defaultValue
            }
            this.errorMessage = this.t('jitsi', 'Failed to load settings')
            console.error('Failed to load setting:', e)
            throw e
        }
    },
}
```

**Imports needed**:
```javascript
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
```

**NC32 Compatibility Note**: This HTTP-based approach works on both NC32 and NC33, so no version detection is needed.

---

### 4.3 HIGH PRIORITY FIX #1: Replace Vue 2 Event Bus with mitt

#### Step 1: Install mitt

**File**: `package.json`

```json
{
    "dependencies": {
        "mitt": "^3.0.0"
        // ... other dependencies
    }
}
```

#### Step 2: Create Event Bus Singleton

**File**: `src/utils/eventBus.js` (NEW)

```javascript
import mitt from 'mitt'

// Create a global event emitter
const eventBus = mitt()

export default eventBus
```

#### Step 3: Update Room.vue

**File**: `src/Room.vue`

Replace all `this.$root.$on`, `this.$root.$once`, `this.$root.$off`, and `this.$root.$emit` calls:

```javascript
// At top of script block
import eventBus from './utils/eventBus'

export default {
    name: 'Room',
    // ... rest of component ...
    
    async created() {
        // ... existing code ...
        
        // Replace:
        // this.$root.$on('jitsi.device_permission_denied', () => {
        // With:
        this.onPermissionDenied = () => {
            this.permissionDenied = true
        }
        eventBus.on('jitsi.device_permission_denied', this.onPermissionDenied)

        this.onSystemTestDone = () => {
            this.systemTestDone = true
        }
        eventBus.on('jitsi.system_test_done', this.onSystemTestDone)

        this.onBrowserStatus = (status) => {
            this.browserStatus = status
        }
        eventBus.on('tol-browser-status', this.onBrowserStatus)

        // ... rest of created() ...
    },

    beforeDestroy() {
        // Clean up event listeners
        eventBus.off('jitsi.device_permission_denied', this.onPermissionDenied)
        eventBus.off('jitsi.system_test_done', this.onSystemTestDone)
        eventBus.off('tol-browser-status', this.onBrowserStatus)
        eventBus.off('mic-stopped', this.onMicStopped)
        eventBus.off('cam-stopped', this.onCamStopped)
        eventBus.off('stop-streams', this.onStopStreams)
    },

    methods: {
        // ... existing methods ...

        async stopStreams() {
            return new Promise((resolve) => {
                let micStopped = false
                let camStopped = false

                this.onMicStopped = () => {
                    micStopped = true
                    if (camStopped) {
                        resolve()
                    }
                }

                this.onCamStopped = () => {
                    camStopped = true
                    if (micStopped) {
                        resolve()
                    }
                }

                eventBus.on('mic-stopped', this.onMicStopped)
                eventBus.on('cam-stopped', this.onCamStopped)

                eventBus.emit('stop-streams')
            })
        },

        async joinBrowser() {
            // ... existing code ...
            
            api.addEventListener('readyToClose', () => {
                this.joining = false
                api.dispose()
                this.conferenceRunning = false
                this.conferenceDone = true
                document.getElementById('header').style.display = ''
                eventBus.emit('resume-preview')
            })
        },
    },
}
```

---

#### Step 4: Update BrowserTest.vue

**File**: `src/components/BrowserTest.vue`

```javascript
// At top of script block
import eventBus from '../utils/eventBus'

export default {
    name: 'BrowserTest',
    // ... rest of component ...
    
    async created() {
        // ... existing code ...

        if (this.status === 'warning') {
            // Replace:
            // this.$root.$emit('tol-browser-status', this.status)
            // With:
            eventBus.emit('tol-browser-status', this.status)
        }
    },
}
```

---

### 4.4 MEDIUM PRIORITY FIX: Migrate localStorage to AppConfig

#### Option A: Simple Migration (Server-side Storage per User)

**Create new controller method** in `lib/Controller/AdminController.php`:

```php
/**
 * @NoAdminRequired
 * @NoCSRFRequired
 * 
 * Set a user preference (stored server-side in user's config)
 */
public function setUserPreference(string $key, string $value): DataResponse {
    $userId = $this->userSession->getUser()?->getUID();
    
    if (!$userId) {
        return new DataResponse(['success' => false], 401);
    }
    
    $this->config->setUserValue(
        $userId,
        Application::APP_ID,
        'pref_' . $key,
        $value
    );
    
    return new DataResponse([
        'key' => $key,
        'value' => $value,
        'success' => true
    ]);
}

/**
 * @NoAdminRequired
 * @NoCSRFRequired
 * 
 * Get a user preference
 */
public function getUserPreference(string $key, ?string $default = null): DataResponse {
    $userId = $this->userSession->getUser()?->getUID();
    
    if (!$userId) {
        return new DataResponse(['value' => $default], 401);
    }
    
    $value = $this->config->getUserValue(
        $userId,
        Application::APP_ID,
        'pref_' . $key,
        $default ?? ''
    );
    
    return new DataResponse([
        'key' => $key,
        'value' => $value,
        'success' => true
    ]);
}
```

**Add routes** in `appinfo/routes.php`:

```php
['name' => 'admin#getUserPreference', 'url' => '/api/preferences/{key}', 'verb' => 'GET'],
['name' => 'admin#setUserPreference', 'url' => '/api/preferences/{key}', 'verb' => 'PUT'],
```

**Update Room.vue** to use the API:

```javascript
// Create utility functions
async function getUserPreference(key, defaultValue = null) {
    try {
        const response = await axios.get(
            generateUrl(`/apps/jitsi/api/preferences/${key}`),
            { params: { default: defaultValue } }
        )
        return response.data.value || defaultValue
    } catch (e) {
        return defaultValue
    }
}

async function setUserPreference(key, value) {
    try {
        const response = await axios.put(
            generateUrl(`/apps/jitsi/api/preferences/${key}`),
            { value }
        )
        return response.data.success
    } catch (e) {
        console.error('Failed to set preference:', e)
        return false
    }
}

// In computed property:
startMuted: {
    get() {
        return this._startMuted
    },
    async set(startMuted) {
        this._startMuted = startMuted
        await setUserPreference('startMuted', startMuted ? '1' : '0')
    },
},
```

---

### 4.5 VERIFICATION REQUIREMENTS

Create a compatibility verification checklist:

#### Backend PHP APIs

```php
// Verify these APIs still exist in NC33
\OCP\Settings\ISettings
\OCP\Settings\IAdminSection
\OCP\AppFramework\Http\ContentSecurityPolicy
\OCP\AppFramework\Http\FeaturePolicy
\OCP\AppFramework\Bootstrap\IBootstrap
\OCP\AppFramework\Bootstrap\IBootContext
\OCP\AppFramework\Bootstrap\IRegistrationContext
\OCP\Db\QueryBuilder\IQueryBuilder (via getQueryBuilder())
\OCP\IConfig (getAppValue, setAppValue, getUserValue, setUserValue)
\OC::$server->getURLGenerator()->linkToRouteAbsolute('core.avatar.getAvatar')
```

#### Frontend JavaScript APIs

```javascript
// Verify these exist or have replacements
@nextcloud/vue/dist/Components/SettingsSection
@nextcloud/axios
@nextcloud/router (generateUrl)
// OCP.AppConfig - REMOVED, use HTTP API instead
// Vue 2 $root - DEPRECATED, use mitt instead
```

---

## Phase 5: Implementation Checklist

### Priority 1 (CRITICAL - Must Do)

- [ ] 1.1 Update `appinfo/info.xml` max-version to 33
- [ ] 1.2 Create `lib/Controller/AdminController.php` with getSetting/setSetting
- [ ] 1.3 Register routes in `appinfo/routes.php`
- [ ] 1.4 Update `src/Admin.vue` methods to use HTTP API
- [ ] 1.5 Install mitt: `npm install mitt@^3.0.0`
- [ ] 1.6 Create `src/utils/eventBus.js`
- [ ] 1.7 Update `src/Room.vue` to use event bus
- [ ] 1.8 Update `src/components/BrowserTest.vue` to use event bus
- [ ] 1.9 Add `beforeDestroy()` in `src/Room.vue` for cleanup

### Priority 2 (HIGH - Do Soon)

- [ ] 2.1 Verify `PageController.php` CSP/FeaturePolicy APIs in NC33
- [ ] 2.2 Verify `UserController.php` avatar route in NC33
- [ ] 2.3 Verify `AdminSettings.php` ISettings interface in NC33
- [ ] 2.4 Verify `RoomMapper.php` QueryBuilder API in NC33
- [ ] 2.5 Test admin settings page load and save on both NC32 and NC33
- [ ] 2.6 Test device selection events in both versions
- [ ] 2.7 Test browser detection in both versions

### Priority 3 (MEDIUM - Plan For)

- [ ] 3.1 Create user preference storage API (getUserPreference/setUserPreference)
- [ ] 3.2 Migrate Room.vue localStorage to AppConfig API
- [ ] 3.3 Replace custom CSS with @nextcloud/vue components
- [ ] 3.4 Verify Navigation registration format works in NC33

### Priority 4 (LOW - Future)

- [ ] 4.1 Plan Vue 3 migration for future versions
- [ ] 4.2 Verify SettingsSection component API in newer @nextcloud/vue versions
- [ ] 4.3 Monitor Jitsi upstream API changes

---

## Phase 6: Testing Matrix

### Environment Setup

| Environment | Version | PHP | Node | npm |
|-------------|---------|-----|------|-----|
| NC32 Test | 32.x latest | 7.4-8.2 | 20+ | 10+ |
| NC33 Test | 33.x latest | 8.0-8.3 | 20+ | 10+ |

### Functional Testing

#### Test Case 1: Admin Settings (NC32)
- [ ] Install app on NC32
- [ ] Navigate to Settings > Jitsi
- [ ] Load settings form (verify no errors in console)
- [ ] Update Jitsi Server URL
- [ ] Verify setting saves successfully
- [ ] Refresh page, verify setting persists
- [ ] Check browser console for errors

#### Test Case 2: Admin Settings (NC33)
- [ ] Install app on NC33
- [ ] Navigate to Settings > Jitsi
- [ ] Load settings form (verify no errors in console)
- [ ] Update JWT settings
- [ ] Verify all settings save successfully
- [ ] Refresh page, verify settings persist
- [ ] Check browser console for errors

#### Test Case 3: Room Access (NC32)
- [ ] Create conference room
- [ ] Access room page
- [ ] Verify BrowserTest loads
- [ ] Verify browser status displays correctly
- [ ] Verify system test runs
- [ ] Check for JavaScript errors

#### Test Case 4: Room Access (NC33)
- [ ] Create conference room
- [ ] Access room page
- [ ] Verify BrowserTest loads
- [ ] Verify browser status displays correctly
- [ ] Verify system test runs
- [ ] Check for JavaScript errors

#### Test Case 5: Device Selection (Both Versions)
- [ ] Run system test
- [ ] Select camera device
- [ ] Select microphone device
- [ ] Select speaker device
- [ ] Verify selections persist during session

#### Test Case 6: User Preferences (Both Versions)
- [ ] Join room
- [ ] Enable "Start muted"
- [ ] Enable "Camera off"
- [ ] Refresh page
- [ ] Verify preferences are still set

---

## Phase 7: Dependency Updates

### package.json Changes

```json
{
    "dependencies": {
        "@nextcloud/axios": "^1.4.0",     // Keep current
        "@nextcloud/router": "^1.2.0",    // Keep current
        "@nextcloud/vue": "^5.0.0",       // Verify works with NC33
        "mitt": "^3.0.0",                 // NEW - Event bus
        "bowser": "^2.11.0",              // Keep current
        "lodash": "^4.17.20",             // Keep current
        "vue": "^2.6.12",                 // Keep current (Vue 2)
        "vue-clipboard2": "^0.3.1",       // Keep current
        "vue-material-design-icons": "^5.2.0" // Keep current
    }
}
```

### composer.json - No Changes Required

The PHP dependencies are stable and compatible with both NC32 and NC33.

---

## Phase 8: Build and Deployment

### Build Process

```bash
# Install dependencies
npm install
npm install mitt@^3.0.0

# Run linter
npm run lint:fix

# Build webpack bundles
npm run build

# Verify build output
ls -la js/
```

### Package Generation

```bash
# Create release package
tar czf jitsi-0.20.0.tar.gz \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='build' \
    --exclude='.npm-cache' \
    .

# Verify structure
tar tzf jitsi-0.20.0.tar.gz | head -20
```

### App Store Requirements

1. Update version in `appinfo/info.xml`: `<version>0.20.0</version>`
2. Update `CHANGELOG.md` with migration notes
3. Add note about NC32 and NC33 compatibility
4. Submit updated app to App Store

---

## Phase 9: Release Notes Template

```markdown
# Jitsi Integration v0.20.0 - Nextcloud 32 & 33 Support

## Features
- ✅ Full support for Nextcloud 33
- ✅ Maintained compatibility with Nextcloud 32
- ✅ Improved settings storage (HTTP-based API)
- ✅ Fixed event bus architecture for Vue compatibility

## Breaking Changes
None. This release is fully backward compatible with NC32.

## Migration Notes
This release updates the internal architecture for better compatibility:
- Admin settings now use HTTP-based API (more reliable)
- Event bus migrated to mitt library (better memory management)
- Settings are now synced server-side (accessible from any device)

No action required from users.

## Known Issues
None.

## Testing
Thoroughly tested on:
- Nextcloud 32.x (PHP 7.4, 8.0, 8.1, 8.2)
- Nextcloud 33.x (PHP 8.0, 8.1, 8.2, 8.3)

## Technical Details
- Replaced deprecated OCP.AppConfig API with modern HTTP endpoints
- Migrated Vue 2 event bus ($root) to mitt library
- Added proper event listener cleanup to prevent memory leaks
- All core functionality working as expected
```

---

## Phase 10: Risk Assessment and Mitigation

### High Risks

#### Risk #1: HTTP Settings API Endpoint Security
**Severity**: High  
**Mitigation**: 
- Implement `@AdminRequired` annotation on endpoints
- Validate all input parameters
- Use CSRF protection (Nextcloud framework handles)
- Test access control thoroughly

#### Risk #2: Event Bus Migration Breaks Communication
**Severity**: High  
**Mitigation**:
- Comprehensive testing of device selection
- Test browser status detection
- Test stream management
- Unit tests for event bus

#### Risk #3: NC33 API Changes Beyond Expected
**Severity**: Medium  
**Mitigation**:
- Comprehensive verification phase before release
- Maintain contact with Nextcloud developers
- Have rollback plan (version constraint to NC32 if needed)

### Medium Risks

#### Risk #4: localStorage Data Loss During Migration
**Severity**: Medium  
**Mitigation**:
- Keep localStorage fallback for initial load
- Migrate data on first access
- Log migration events

#### Risk #5: User Preferences Not Syncing
**Severity**: Medium  
**Mitigation**:
- Comprehensive testing across devices
- Verify database writes
- Monitor logs for errors

---

## Phase 11: Rollback Plan

If NC33 compatibility proves problematic:

1. **Immediate**: Revert `max-version` to 31 in info.xml
2. **Short-term**: Maintain NC32 support with known workarounds
3. **Long-term**: Detailed NC33 investigation with contributors

---

## Conclusion and Recommendations

### Can NC32 + NC33 Support Be Maintained from One Branch?

**YES - STRONGLY RECOMMENDED**

The technical feasibility is high:
- No breaking changes between NC32 and NC33 in core APIs used by this app
- Mitt library provides complete backward/forward compatibility
- HTTP-based settings API works on both versions
- Version detection is minimal and localized

### Recommended Implementation Path

1. **Branch Strategy**: Single main branch
2. **Version Support**: NC25-33
3. **Release Strategy**: One version supports both NC32 and NC33
4. **Testing**: Automated tests on both NC32 and NC33
5. **Maintenance**: Standard semantic versioning

### Expected Timeline

| Phase | Effort | Timeline |
|-------|--------|----------|
| Implementation | 40-60 hours | 1-2 weeks |
| Testing | 15-20 hours | 1 week |
| Review/Refinement | 10-15 hours | 3-5 days |
| Release Prep | 5 hours | 1-2 days |
| **Total** | **70-100 hours** | **4-5 weeks** |

### Success Criteria

✅ App installs on NC32 and NC33 without modification  
✅ All settings load and save correctly  
✅ Device selection works reliably  
✅ Browser detection functions properly  
✅ No JavaScript console errors  
✅ No PHP error logs  
✅ All automated tests pass  
✅ Manual testing complete on both versions  

---

## Appendix A: File Changes Summary

### New Files to Create
1. `lib/Controller/AdminController.php` - Settings API endpoints
2. `src/utils/eventBus.js` - Global event bus

### Files to Modify
1. `appinfo/info.xml` - Update max-version (1 line)
2. `appinfo/routes.php` - Add new API routes (3 lines)
3. `src/Admin.vue` - Replace settings methods (40 lines)
4. `src/Room.vue` - Event bus migration + cleanup (80 lines)
5. `src/components/BrowserTest.vue` - Event bus migration (5 lines)
6. `package.json` - Add mitt dependency (1 line)

### Total Changes: ~130 lines added/modified

---

## Appendix B: Key References

### Nextcloud Documentation
- [NC32 Upgrade Guide](https://docs.nextcloud.com/server/latest/)
- [NC33 Upgrade Guide](https://docs.nextcloud.com/server/latest/)
- [OCP API Documentation](https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCP/index.html)

### Nextcloud Vue Components
- [@nextcloud/vue v5+ Docs](https://nextcloud-vue.netlify.app)
- [SettingsSection API](https://nextcloud-vue.netlify.app/#/Components/SettingsSection)

### External Libraries
- [mitt - Event Bus](https://github.com/developit/mitt)
- [axios HTTP Client](https://github.com/axios/axios)
- [@nextcloud/router](https://github.com/nextcloud/nextcloud-vue)

---

**Document Version**: 1.0  
**Last Updated**: June 11, 2026  
**Status**: Ready for Implementation
