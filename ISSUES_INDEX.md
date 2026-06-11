# Nextcloud Jitsi App - NC32/NC33 Compatibility Issues Summary

## Quick Reference Index

### CRITICAL ISSUES (Must Fix Before NC32/NC33)

| File | Line(s) | Issue | Severity | Action |
|------|---------|-------|----------|--------|
| [appinfo/info.xml](appinfo/info.xml#L27) | 27 | max-version="31" blocks NC32/33 | 🔴 CRITICAL | Update max-version to 33 |
| [src/Admin.vue](src/Admin.vue#L244-L268) | 244, 257 | OCP.AppConfig deprecated API | 🔴 CRITICAL | Replace with HTTP API |

---

### HIGH PRIORITY ISSUES

| File | Line(s) | Issue | Severity | Action |
|------|---------|-------|----------|--------|
| [src/Room.vue](src/Room.vue#L284-L294) | 284-294, 494-504, 521 | $root.$on/emit event bus pattern (Vue 2) | 🟠 HIGH | Implement mitt or Pinia |
| [src/Room.vue](src/Room.vue#L278) | 278-294 | Missing event listener cleanup | 🟠 HIGH | Add beforeDestroy() hook |
| [src/components/BrowserTest.vue](src/components/BrowserTest.vue#L101) | 101 | $root.$emit used (Vue 2 pattern) | 🟠 HIGH | Implement mitt or Pinia |
| [lib/Controller/PageController.php](lib/Controller/PageController.php#L83-94) | 83-94 | FeaturePolicy/ContentSecurityPolicy API | 🟠 HIGH | Verify against NC33 docs |
| [lib/Controller/UserController.php](lib/Controller/UserController.php#L67) | 67 | Avatar route compatibility | 🟠 HIGH | Verify core.avatar.getAvatar route |
| [lib/Settings/AdminSettings.php](lib/Settings/AdminSettings.php) | All | AdminSettings interface compatibility | 🟠 HIGH | Verify ISettings interface |

---

### MEDIUM PRIORITY ISSUES

| File | Line(s) | Issue | Severity | Action |
|------|---------|-------|----------|--------|
| [src/Room.vue](src/Room.vue#L290-291, 300-301, 516-517, 521) | Multiple | localStorage for user preferences | 🟡 MEDIUM | Migrate to per-user AppConfig |
| [lib/Db/RoomMapper.php](lib/Db/RoomMapper.php#L25,39,57,88) | 25, 39, 57, 88 | QueryBuilder usage | 🟡 MEDIUM | Verify QueryBuilder API |
| [appinfo/info.xml](appinfo/info.xml#L37-41) | 37-41 | Legacy navigation registration | 🟡 MEDIUM | Plan migration to registerNavigationEntry() |
| [src/Admin.vue](src/Admin.vue) | Multiple | Custom CSS instead of NcComponents | 🟡 MEDIUM | Use @nextcloud/vue components |
| [lib/Config/Config.php](lib/Config/Config.php) | All | AppConfig usage pattern | 🟡 MEDIUM | Verify IConfig API in NC33 |

---

### LOW PRIORITY ISSUES

| File | Line(s) | Issue | Severity | Action |
|------|---------|-------|----------|--------|
| [src/Admin.vue](src/Admin.vue#L5,146) | 5, 146 | SettingsSection component API | 🟢 LOW | Verify component props/slots |
| [src/Room.vue](src/Room.vue#L176) | 176 | External Jitsi API import | 🟢 LOW | Verify API stability |
| [src/**/*.vue](src) | Multiple | Vue 2 lifecycle hooks | 🟢 LOW | Plan Vue 3 migration |

---

## File-by-File Detailed List

### lib/AppInfo/Application.php
- **Status**: ✓ Likely compatible
- **APIs Used**: `IBootstrap`, `IRegistrationContext`, `IBootContext`
- **Action**: Verify against NC33, test in environment

### lib/Config/Config.php
- **Status**: ⚠️ Verify
- **APIs Used**: `IConfig::getAppValue()`, `IConfig::setAppValue()`
- **Lines**: 31-64
- **Action**: Verify these methods still exist in NC33

### lib/Controller/AbstractController.php
- **Status**: ✓ Likely compatible
- **APIs Used**: `Controller`, `TemplateResponse`, `IUserSession`
- **Action**: Test with NC33

### lib/Controller/AssetsController.php
- **Status**: ✓ Likely compatible
- **APIs Used**: `Controller`, `StreamResponse`
- **Action**: Test with NC33

### lib/Controller/PageController.php
- **Issues Found**:
  - Line 8-9: `ContentSecurityPolicy`, `FeaturePolicy` imports
  - Line 83-94: `setContentSecurityPolicy()`, `setFeaturePolicy()` methods
- **Action**: Verify these APIs exist in NC33
- **Severity**: 🟠 HIGH

### lib/Controller/RoomController.php
- **Status**: ✓ Mostly compatible
- **APIs Used**: `DataResponse`, `Http` constants
- **Action**: Test with NC33

### lib/Controller/UserController.php
- **Issues Found**:
  - Line 67: `linkToRouteAbsolute('core.avatar.getAvatar', ...)`
- **Action**: Verify route exists in NC33
- **Severity**: 🟠 HIGH

### lib/Db/Room.php
- **Status**: ✓ Likely compatible
- **APIs Used**: `Entity`
- **Action**: Test with NC33

### lib/Db/RoomMapper.php
- **Issues Found**:
  - Line 25, 39, 57, 88: `getQueryBuilder()` usage
  - Line 58: `expr()->iLike()` method
- **Action**: Verify QueryBuilder API in NC33
- **Severity**: 🟡 MEDIUM

### lib/Migration/Version10000Date20201018172823.php
- **Status**: ✓ Likely compatible
- **APIs Used**: `ISchemaWrapper`, `SimpleMigrationStep`
- **Action**: Test with NC33

### lib/Search/Provider.php
- **Status**: ✓ Likely compatible
- **APIs Used**: `IProvider`, `SearchResult`, `SearchResultEntry`
- **Action**: Verify interface in NC33

### lib/Settings/AdminSection.php
- **Status**: ✓ Likely compatible
- **APIs Used**: `IIconSection`
- **Action**: Test with NC33

### lib/Settings/AdminSettings.php
- **Issues Found**:
  - All lines: `ISettings` interface implementation
- **Action**: Verify interface in NC33
- **Severity**: 🟠 HIGH

### appinfo/info.xml
- **Issues Found**:
  - Line 27: `max-version="31"`
  - Line 37-41: Legacy navigation registration
- **Action**: Update max-version AND plan navigation migration
- **Severity**: 🔴 CRITICAL + 🟡 MEDIUM

### appinfo/routes.php
- **Status**: ✓ Routing format still valid
- **Action**: Test routes work in NC33

### src/admin.js
- **Status**: ✓ Vue 2 initialization, will work if Admin.vue fixed
- **Action**: Test with NC33

### src/index.js
- **Status**: ✓ Vue 2 initialization, similar to admin.js
- **Action**: Test with NC33

### src/room.js
- **Status**: ✓ Vue 2 initialization
- **Action**: Test with NC33

### src/Admin.vue
- **Issues Found**:
  - Line 5, 146: `SettingsSection` from @nextcloud/vue
  - Line 244, 257: `OCP.AppConfig.setValue()`, `OCP.AppConfig.getValue()`
  - Line 256-268: XML response parsing
  - Multiple: Custom CSS classes instead of NcComponents
- **Action**: Replace OCP.AppConfig API, update components
- **Severity**: 🔴 CRITICAL + 🟡 MEDIUM

### src/Index.vue
- **Status**: ✓ Likely compatible
- **APIs Used**: `axios`, `generateUrl`
- **Action**: Test with NC33

### src/Room.vue
- **Issues Found**:
  - Line 176: `JitsiMeetExternalAPI` import
  - Line 237-238, 279: `window.location` access
  - Line 284-294: `this.$root.$on()` event bus
  - Line 290-291, 300-301: `localStorage.getItem/setItem`
  - Line 494-504, 521: Event bus emit/on pattern
  - Line 516-517: `localStorage.setItem()`
- **Action**: Replace event bus pattern, migrate localStorage
- **Severity**: 🟠 HIGH + 🟡 MEDIUM

### src/components/BrowserTest.vue
- **Issues Found**:
  - Line 101: `this.$root.$emit()`
- **Action**: Replace with event bus pattern
- **Severity**: 🟠 HIGH

### src/components/CameraTest.vue
- **Status**: ✓ Component likely compatible
- **Action**: Test with NC33

### src/components/CheckStatusIcon.vue
- **Status**: ✓ Icon component
- **Action**: Test with NC33

### src/components/CreateRoomItem.vue
- **Status**: ✓ Standard Vue component
- **APIs Used**: `axios.post()`, `generateUrl()`
- **Action**: Test with NC33

### src/components/EmptyRoomListItem.vue
- **Status**: ✓ Standard Vue component
- **APIs Used**: `axios.post()`, `generateUrl()`
- **Action**: Test with NC33

### src/components/MicTest.vue
- **Status**: ✓ Component likely compatible
- **Action**: Test with NC33

### src/components/RoomList.vue
- **Status**: ✓ Container component
- **Action**: Test with NC33

### src/components/RoomListItem.vue
- **Issues Found**:
  - Line 74-75: `window.location` access
- **Action**: Test with NC33
- **Severity**: 🟢 LOW

### src/components/RoomNotFound.vue
- **Status**: ✓ Simple component
- **Action**: Test with NC33

### src/components/SpeakerTest.vue
- **Status**: ✓ Component likely compatible
- **Action**: Test with NC33

### src/components/SystemTest.vue
- **Status**: ✓ Component likely compatible
- **Action**: Test with NC33

### src/mixins/AppGlobal.js
- **Status**: ✓ Simple mixin
- **Action**: Test with NC33

---

## Statistics

### Files with Issues by Severity

```
🔴 CRITICAL: 2 files
   - appinfo/info.xml
   - src/Admin.vue

🟠 HIGH: 6 files
   - src/Room.vue
   - src/components/BrowserTest.vue
   - lib/Controller/PageController.php
   - lib/Controller/UserController.php
   - lib/Settings/AdminSettings.php
   - [Potentially] lib/Config/Config.php

🟡 MEDIUM: 5 files
   - src/Room.vue (multiple issues)
   - src/Admin.vue (additional issues)
   - lib/Db/RoomMapper.php
   - appinfo/info.xml (navigation pattern)
   - lib/Config/Config.php

🟢 LOW: 5 files
   - src/Admin.vue (CSS)
   - src/components/RoomListItem.vue
   - Multiple Vue files (lifecycle hooks)
```

### Issue Summary

| Severity | Count | Files Affected |
|----------|-------|-----------------|
| 🔴 CRITICAL | 2 | 2 |
| 🟠 HIGH | 6 | 6 |
| 🟡 MEDIUM | 5 | 5 |
| 🟢 LOW | 5 | 5 |
| **TOTAL** | **18** | **12** |

---

## Remediation Priority

1. **Phase 1 - CRITICAL (Must do)**:
   - Update info.xml max-version
   - Replace OCP.AppConfig API

2. **Phase 2 - HIGH (Do soon)**:
   - Implement event bus pattern
   - Add event listener cleanup
   - Verify Policy APIs
   - Verify Settings interface

3. **Phase 3 - MEDIUM (Plan)**:
   - Migrate localStorage to AppConfig
   - Update component library usage
   - Plan navigation migration

4. **Phase 4 - LOW (Future)**:
   - Migrate to Vue 3 composition API
   - Update to newer component patterns

---

## Testing Environment

Recommended testing:
- [ ] Local NC32 test instance
- [ ] Local NC33 test instance
- [ ] All browsers (Chrome, Firefox, Safari, Edge)
- [ ] Admin panel save/load
- [ ] Room creation and joining
- [ ] System test functionality

---

Generated: June 11, 2026
