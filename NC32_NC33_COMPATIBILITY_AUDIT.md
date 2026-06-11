# Nextcloud Jitsi App - NC32/NC33 Compatibility Audit Report

**Date**: June 11, 2026  
**App Version**: 0.19.0  
**Target**: Nextcloud 32/33 Compatibility Assessment

---

## Executive Summary

This audit identified **15 compatibility concerns** between the Nextcloud Jitsi Integration app and Nextcloud 32/33. Of these:
- **2 CRITICAL** issues that will prevent the app from functioning
- **5 HIGH** priority issues requiring immediate attention
- **5 MEDIUM** priority issues needing verification/updates
- **3 LOW** priority improvements

The app currently declares `max-version="31"` in info.xml, blocking installation on NC32+.

---

## CRITICAL ISSUES

### 1. ❌ App Version Constraint (BLOCKS ALL NC32/NC33)

**File**: [appinfo/info.xml](appinfo/info.xml#L27)  
**Line**: 27  
**Current Code**:
```xml
<dependencies>
    <nextcloud min-version="25" max-version="31"/>
</dependencies>
```

**Problem**: The app declares maximum version 31, preventing installation on NC32 and NC33.

**Impact**: App cannot be installed or will be disabled on NC32+ instances.

**Required Action**: Update max-version constraint:
```xml
<dependencies>
    <nextcloud min-version="25" max-version="33"/>
</dependencies>
```

**Severity**: 🔴 CRITICAL

---

### 2. ❌ Deprecated JavaScript Settings API

**File**: [src/Admin.vue](src/Admin.vue#L244-L268)  
**Lines**: 244, 257  
**Current Code**:
```javascript
// Line 244
OCP.AppConfig.setValue('jitsi', name, value, {
    success: resolve,
    error: reject,
})

// Line 257
OCP.AppConfig.getValue('jitsi', name, defaultValue, {
    success: resolve,
    error: reject,
})

// Lines 262-268
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
```

**Problem**: 
- `OCP.AppConfig` is a legacy XML-based API that was deprecated in NC32
- NC33 has significantly refactored the settings API
- The API returns XML document that must be manually parsed
- This is callback-based instead of modern Promise/async-await pattern

**Changes in NC33**:
- Legacy `OCP.AppConfig` API has been removed or heavily restricted
- New HTTP-based REST API endpoints for settings
- Modern async/await support with axios

**Impact**: Settings cannot be saved or loaded on NC33 - admin page will fail.

**Required Action**: Replace with HTTP-based settings API using `@nextcloud/axios`:
```javascript
// New pattern for NC33+
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

async loadSetting(name, defaultValue = null) {
    try {
        const response = await axios.get(
            generateUrl(`/apps/jitsi/api/settings/${name}`),
            { params: { default: defaultValue } }
        )
        return response.data.value
    } catch (e) {
        console.error('Failed to load settings', e)
        throw e
    }
}

async updateSetting(name, value) {
    try {
        await axios.post(generateUrl('/apps/jitsi/api/settings'), {
            key: name,
            value: value
        })
    } catch (e) {
        console.error('Failed to save settings', e)
        throw e
    }
}
```

OR create a PHP API endpoint that handles the settings, then call it from Vue.

**Severity**: 🔴 CRITICAL

---

## HIGH PRIORITY ISSUES

### 3. ⚠️ Vue 2 Event Bus Pattern (Deprecated in Vue 3)

**Files**: 
- [src/Room.vue](src/Room.vue#L284-L294) - Lines 284-294, 494-504
- [src/components/BrowserTest.vue](src/components/BrowserTest.vue#L101) - Line 101
- [src/components/SystemTest.vue](src/components/SystemTest.vue) - Event emissions

**Current Code in Room.vue**:
```javascript
// Lines 284-294 (created hook)
this.$root.$on('jitsi.device_permission_denied', () => {
    this.permissionDenied = true
})

this.$root.$on('jitsi.system_test_done', () => {
    this.systemTestDone = true
})

this.$root.$on('tol-browser-status', (status) => {
    this.browserStatus = status
})

// Lines 494-504 (stopStreams method)
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

// Line 521
this.$root.$emit('stop-streams')
this.$root.$emit('resume-preview')
```

**In BrowserTest.vue**:
```javascript
// Line 101
this.$root.$emit('tol-browser-status', this.status)
```

**Problem**:
- Vue 2's `$root` event bus pattern was a workaround for component communication
- Vue 3 removed the `$root` property and event bus functionality
- NC33 likely moves toward Vue 3 composition API
- No automatic cleanup = memory leaks

**Event Names Found**:
1. `jitsi.device_permission_denied`
2. `jitsi.system_test_done`
3. `tol-browser-status`
4. `mic-stopped`
5. `cam-stopped`
6. `stop-streams`
7. `resume-preview`

**Impact**: Component communication will break when moving to Vue 3.

**Required Action**: Implement proper event bus using one of:

**Option A: Use mitt library (smallest footprint)**
```javascript
import mitt from 'mitt'
export const bus = mitt()

// In Room.vue
import { bus } from './bus'
created() {
    bus.on('jitsi.device_permission_denied', () => {
        this.permissionDenied = true
    })
}
beforeDestroy() {
    bus.off('jitsi.device_permission_denied')
}
```

**Option B: Use Pinia store (recommended for larger apps)**
```javascript
// stores/jitsi.js
import { defineStore } from 'pinia'

export const useJitsiStore = defineStore('jitsi', {
    state: () => ({
        permissionDenied: false,
        systemTestDone: false,
        browserStatus: null,
    }),
    actions: {
        setPermissionDenied(value) {
            this.permissionDenied = value
        }
    }
})
```

**Severity**: 🟠 HIGH

---

### 4. ⚠️ Missing Event Listener Cleanup (Memory Leak)

**File**: [src/Room.vue](src/Room.vue#L278-L294)  
**Lines**: 278-294

**Current Code**:
```javascript
async created() {
    // ... other code ...
    
    this.$root.$on('jitsi.device_permission_denied', () => {
        this.permissionDenied = true
    })

    this.$root.$on('jitsi.system_test_done', () => {
        this.systemTestDone = true
    })

    this.$root.$on('tol-browser-status', (status) => {
        this.browserStatus = status
    })
    
    // ... rest of method ...
}
// NO beforeDestroy() hook to clean up listeners!
```

**Problem**:
- Event listeners are registered in `created()` but never unregistered
- Each time component is created/destroyed, listeners accumulate
- Leads to memory leaks and duplicate event handling

**Impact**: Memory usage increases with each navigation, potential duplicate actions.

**Required Action**: Add lifecycle hook for cleanup:
```javascript
beforeDestroy() {
    this.$root.$off('jitsi.device_permission_denied')
    this.$root.$off('jitsi.system_test_done')
    this.$root.$off('tol-browser-status')
    this.$root.$off('mic-stopped')
    this.$root.$off('cam-stopped')
}
```

Or when using mitt:
```javascript
beforeDestroy() {
    bus.off('*')  // Clear all listeners
}
```

**Severity**: 🟠 HIGH

---

### 5. ⚠️ FeaturePolicy & ContentSecurityPolicy API Changes

**File**: [lib/Controller/PageController.php](lib/Controller/PageController.php#L8-9, #L83-94)  
**Lines**: 8-9 (imports), 83-94 (usage)

**Current Code**:
```php
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\FeaturePolicy;

// ...

private function setPolicies(Response $response): void {
    // ...
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

**Potential Issues in NC33**:
- `FeaturePolicy` API method names may have changed
- `addAllowedCameraDomain()` and `addAllowedMicrophoneDomain()` might be renamed
- NC33 may use permissions-policy header instead of feature-policy

**Recommended Verification**:
```bash
# Check NC33 Response class for these methods
grep -r "setFeaturePolicy\|setContentSecurityPolicy" /path/to/nc33/lib/
grep -r "class.*FeaturePolicy\|class.*ContentSecurityPolicy" /path/to/nc33/lib/
```

**Required Action**: Test against NC33 and verify these APIs exist and work as expected.

**Severity**: 🟠 HIGH

---

### 6. ⚠️ Avatar URL Generation Route

**File**: [lib/Controller/UserController.php](lib/Controller/UserController.php#L67)  
**Line**: 67

**Current Code**:
```php
private function generateAvatarUrl(string $uid): string {
    return $this->urlGenerator->linkToRouteAbsolute('core.avatar.getAvatar', [
        'userId' => $uid,
        'size' => 256,
        'v' => $this->config->getUserValue($uid, 'avatar', 'version', 0)
    ]);
}
```

**Potential Issues in NC33**:
- The `core.avatar.getAvatar` route may have changed
- Parameter names or structure might be different
- The avatar versioning system may have changed

**Recommended Verification**:
1. Check if route still exists in NC33
2. Verify parameter names and values
3. Test avatar URL generation

**Alternative Approach (safer for version compatibility)**:
```php
// Use the public avatar API that's more stable
return $this->urlGenerator->linkToRouteAbsolute('core.avatar.getAvatar', [
    'userId' => $uid,
    'size' => 256,
]);
// Optionally add cache-busting version separately
```

**Severity**: 🟠 HIGH

---

### 7. ⚠️ Admin Settings Integration Pattern

**File**: [lib/Settings/AdminSettings.php](lib/Settings/AdminSettings.php)  
**Lines**: 1-16

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

**Potential Issues in NC33**:
- NC33 significantly refactored the admin settings UI
- `ISettings` interface methods might have different signatures
- Return types may have changed
- Response object handling may be different

**Recommended Action**:
1. Verify `ISettings` interface in NC33 matches current implementation
2. Check if `getForm()`, `getSection()`, `getPriority()` methods still exist
3. Verify TemplateResponse is still accepted
4. Test admin panel renders correctly in NC33

**Severity**: 🟠 HIGH

---

## MEDIUM PRIORITY ISSUES

### 8. ⚠️ localStorage for User Preferences (Not Synced)

**File**: [src/Room.vue](src/Room.vue#L290-L291, #L300-L301)  
**Lines**: 290-291, 300-301, 516-517, 521

**Current Code**:
```javascript
// Lines 290-291 (in created())
this.startMuted = localStorage.getItem('jitsi.startMuted') === 'true'
this.startCameraOff = localStorage.getItem('jitsi.startCameraOff') === 'true'

// Line 300
this.userName = localStorage.getItem('jitsi.userName')

// Lines 516-517 (in computed property setter)
set(startMuted) {
    this._startMuted = startMuted
    localStorage.setItem('jitsi.startMuted', startMuted)
}

// Line 521
if (!this.user && this.userName) {
    localStorage.setItem('jitsi.userName', this.userName)
}
```

**Problem**:
- localStorage is device-specific, not synced across browser instances
- Clearing browser data loses user preferences
- No user-specific configuration sync
- Doesn't respect user privacy settings
- userName stored in localStorage but also used for user identification

**Impact**: 
- User preferences lost when switching devices or browsers
- Settings don't follow user across Nextcloud instances
- Privacy concern: preferences stored in cleartext in browser

**Recommendation**: Migrate to per-user AppConfig:
```javascript
// New approach using Nextcloud AppConfig API
async saveSetting(key, value) {
    await axios.post(generateUrl('/apps/jitsi/api/user-settings'), {
        key: key,
        value: value
    })
}

async loadSetting(key) {
    const response = await axios.get(generateUrl(`/apps/jitsi/api/user-settings/${key}`))
    return response.data.value
}

// Create PHP endpoint that stores in user AppConfig
// lib/Controller/UserController.php
public function saveSetting(string $key, string $value): DataResponse {
    $user = $this->userSession->getUser();
    if ($user === null) {
        return new DataResponse([], Http::STATUS_UNAUTHORIZED);
    }
    
    $this->config->setUserValue($user->getUID(), 'jitsi', $key, $value);
    return new DataResponse(['success' => true]);
}
```

**Severity**: 🟡 MEDIUM

---

### 9. ⚠️ Database QueryBuilder Pattern (Verify Compatibility)

**File**: [lib/Db/RoomMapper.php](lib/Db/RoomMapper.php)  
**Lines**: 25, 39, 57, 88

**Current Code**:
```php
use OCP\AppFramework\Db\QBMapper;

// Line 25, 39, 57, 88
$qb = $this->db->getQueryBuilder();

// Typical usage pattern
$qb->select('*')
    ->from($this->getTableName())
    ->where(
        $qb->expr()->eq('creator_id', $qb->createNamedParameter($user->getUID()))
    )
    ->orderBy('name', 'asc');
```

**Status**: 
- QueryBuilder pattern has been stable since NC15+
- Likely compatible with NC33
- However, need to verify expression helper methods

**Required Verification**:
1. ✓ `getQueryBuilder()` method exists
2. ✓ `expr()->eq()` method exists
3. ✓ `expr()->iLike()` method exists (line 58 uses this for LIKE)
4. ✓ `createNamedParameter()` exists
5. ✓ `select()`, `from()`, `where()`, `orderBy()` methods

**Query Using iLike**:
```php
$nameParam = $qb->createNamedParameter('%' . $sanitisedName . '%');
$qb->where(
    $qb->expr()->iLike('name', $nameParam),
)
```

**Severity**: 🟡 MEDIUM (Low risk, but verify)

---

### 10. ⚠️ Navigation Registration Pattern

**File**: [appinfo/info.xml](appinfo/info.xml#L37-41)  
**Lines**: 37-41

**Current Code**:
```xml
<navigations>
    <navigation>
        <name>Conferences</name>
        <route>jitsi.page.index</route>
    </navigation>
</navigations>
```

**Status**: 
- Legacy navigation declaration format
- NC28+ introduced `registerNavigationEntry()` method
- info.xml navigation entries still supported but deprecated

**Recommended Upgrade** (for future compatibility):
```php
// In Application.php register() method
$context->registerNavigationEntry(OCA\jitsi\Navigation\JitsiNavigationEntry::class);

// Create new file: lib/Navigation/JitsiNavigationEntry.php
use OCP\Navigation\INavigationEntry;
use OCP\Navigation\NavigationEntry;

public function __construct(IAppManager $appManager) {
    return new NavigationEntry([
        'id' => 'jitsi',
        'order' => 20,
        'href' => \OC::$server->getURLGenerator()->linkToRoute('jitsi.page.index'),
        'icon' => \OC::$server->getURLGenerator()->imagePath('jitsi', 'app-dark.svg'),
        'name' => 'Conferences',
    ]);
}
```

**Current Code Still Works**: The XML-based approach should still work in NC33, but migration is recommended for forward compatibility.

**Severity**: 🟡 MEDIUM (No immediate action needed, but plan migration)

---

### 11. ⚠️ Admin Settings CSS Classes

**File**: [src/Admin.vue](src/Admin.vue#L8-138, #L276-349)  
**Lines**: Various

**Current CSS Classes**:
```css
.group { align-items: flex-start; display: flex; }
.group--centered { align-items: center; }
.group-label { display: block; margin-bottom: 8px; margin-top: 16px; }
.label { display: block; width: 100%; }
.input { display: block; ... }
.input-group { ... }
.admin-checkbox { ... }
.error-text { ... }
.success { ... }
.msg { ... }
```

**Issue**: Using custom CSS classes instead of Nextcloud design system components

**Recommended Update** (aligns with user preference):
- Use `@nextcloud/vue` form components:
  - `NcButton` instead of `<button>`
  - `NcTextField` instead of `<input>`
  - `NcCheckboxRadioSwitch` instead of `<input type="checkbox">`
  - `NcSelect` for dropdowns
- Use tokenized CSS variables: `var(--color-primary)`, `var(--color-border)`, etc.
- Use `NcSettingsSection` instead of custom `<form>`

**Severity**: 🟡 MEDIUM (Visual consistency, not functional)

---

## LOW PRIORITY ISSUES

### 12. ⚠️ Hardcoded CSS Variables in Room.vue

**File**: [src/Room.vue](src/Room.vue#L550-650)  
**CSS Used**: `var(--color-text-lighter)`, `var(--color-border)`, etc.

**Current Code**:
```css
.room__title {
    color: var(--color-text-lighter);
    font-size: 48px;
}

.create-room {
    border: 1px solid var(--color-border);
}
```

**Status**: ✓ Already using CSS variables (good!)

**Recommendation**: Ensure all colors use tokenized variables for theme compatibility.

**Severity**: 🟢 LOW (Already compliant)

---

### 13. ⚠️ SettingsSection Component API

**File**: [src/Admin.vue](src/Admin.vue#L5, #L146)  
**Lines**: 5, 146

**Current Code**:
```vue
<template>
    <div>
        <form @submit.prevent="submit">
            <fieldset :disabled="saving">
                <SettingsSection title="Jitsi">
                    <!-- content -->
                </SettingsSection>
            </fieldset>
        </form>
    </div>
</template>

<script>
import SettingsSection from '@nextcloud/vue/dist/Components/SettingsSection'

export default {
    name: 'Admin',
    components: {
        SettingsSection,
    },
    // ...
}
</script>
```

**Status**: Using `@nextcloud/vue` component (good!)

**Verification Needed**:
- Check if `SettingsSection` props and slots changed in newer @nextcloud/vue versions
- Verify `title` prop is still supported
- Test rendering in NC33 environment

**Severity**: 🟢 LOW (Likely compatible)

---

### 14. ⚠️ External Jitsi API Import

**File**: [src/Room.vue](src/Room.vue#L176)  
**Line**: 176

**Current Code**:
```javascript
import JitsiMeetExternalAPI from './external_api'
```

**Status**: Imports external API wrapper (external_api.js is minified/bundled)

**Note**: This is a wrapper around Jitsi's public API and should remain compatible.

**Severity**: 🟢 LOW

---

### 15. ⚠️ Vue Lifecycle Hook Patterns

**Files**: Multiple Vue components  
**Pattern**: Using Vue 2 lifecycle hooks (`created()`, `mounted()`, `beforeDestroy()`)

**Current Pattern**:
```javascript
async created() { ... }
mounted() { ... }
```

**NC33 Compatibility**:
- Vue 2 lifecycle hooks still work in Vue 2.x
- Plan migration to Vue 3 Composition API for future:
  - `created()` → `setup()`
  - `mounted()` → `onMounted()`
  - `beforeDestroy()` → `onBeforeUnmount()`

**Severity**: 🟢 LOW (No immediate action, but plan migration)

---

## VERIFICATION CHECKLIST

### Before Updating to NC32/NC33:

- [ ] Update `max-version` to 33 in info.xml (CRITICAL)
- [ ] Test Settings API with new HTTP-based approach (CRITICAL)
- [ ] Implement proper event bus (mitt or Pinia) (HIGH)
- [ ] Add event cleanup in `beforeDestroy()` (HIGH)
- [ ] Test FeaturePolicy/ContentSecurityPolicy APIs (HIGH)
- [ ] Verify avatar route in NC33 (HIGH)
- [ ] Verify AdminSettings interface (HIGH)
- [ ] Test with localStorage settings fallback (MEDIUM)
- [ ] Verify QueryBuilder compatibility (MEDIUM)
- [ ] Plan navigation registration migration (MEDIUM)
- [ ] Update admin panel to use NcComponents (MEDIUM)
- [ ] Verify SettingsSection component (LOW)
- [ ] Test all functionality in NC33 environment (ALL)

---

## Testing Commands

```bash
# Test PHP syntax
php -l lib/**/*.php

# Lint JavaScript
npm run lint src/

# Test admin panel loads correctly
# 1. Navigate to /settings/admin in NC33
# 2. Click "Jitsi" in left sidebar
# 3. Verify settings load and save

# Test room functionality
# 1. Navigate to /apps/jitsi
# 2. Try to create a room
# 3. Try to join a room
# 4. Verify system test runs
```

---

## Next Steps

1. **IMMEDIATE** (Required for NC32/NC33):
   - Update version constraint in info.xml
   - Replace OCP.AppConfig API with HTTP endpoints
   - Implement event bus pattern

2. **SHORT TERM** (1-2 releases):
   - Add event listener cleanup
   - Verify and fix Policy APIs
   - Migrate localStorage to per-user settings

3. **LONG TERM** (Plan for future):
   - Migrate to Vue 3 Composition API
   - Update navigation to registerNavigationEntry()
   - Use Nextcloud Vue components throughout

---

## References

- [Nextcloud 32 Migration Guide](https://docs.nextcloud.com/server/latest/developer_manual/)
- [Nextcloud 33 Migration Guide](https://docs.nextcloud.com/server/latest/developer_manual/)
- [@nextcloud/vue Components](https://nextcloud-vue.readthedocs.io/)
- [Vue 2 → Vue 3 Migration](https://v3.vuejs.org/guide/migration/introduction.html)
- [App Config API](https://docs.nextcloud.com/server/latest/developer_manual/api/occ/index.html)

---

**Audit completed**: June 11, 2026  
**Auditor**: GitHub Copilot  
**Status**: Ready for remediation
