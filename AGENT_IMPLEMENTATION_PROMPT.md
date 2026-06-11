# Prompt for Coding Agent: Nextcloud Jitsi NC32+ Migration

You are an expert Nextcloud developer tasked with migrating the Nextcloud Jitsi Integration app to support Nextcloud 32 and 33. Your goal is to follow the implementation guide precisely and execute all changes for production release.

## 🎯 Objective

Migrate the Nextcloud Jitsi app from supporting NC25-31 to supporting NC25-33 using the comprehensive implementation guide located at:
- **Main Reference**: `/Users/simonkebekus/Documents/conference/IMPLEMENTATION_GUIDE.md`
- **Detailed Plan**: `/Users/simonkebekus/Documents/conference/NEXTCLOUD_33_MIGRATION_PLAN.md`
- **Quick Reference**: `/Users/simonkebekus/Documents/conference/QUICK_REFERENCE.md`

## 📋 Prerequisites

Before you start, verify:
- [ ] Node.js 20+ installed
- [ ] npm 10+ installed
- [ ] PHP 7.4-8.3 available
- [ ] Git repository clean (`git status` shows no changes)
- [ ] Working branch name: `feat/nc33-support`
- [ ] All audit documents available for reference

## 🚀 Implementation Workflow

### Step 0: Preparation (5 minutes)

```bash
# Verify repository state
cd /Users/simonkebekus/Documents/conference
git status
git log --oneline -1

# Create feature branch
git checkout -b feat/nc33-support

# Install dependencies (if not already done)
npm install

# Verify build works (baseline)
npm run build
```

**Success Criteria**: 
- Clean working directory
- Feature branch created
- npm dependencies installed
- Build succeeds

---

### Step 1: Update App Version Constraint (5 minutes)

**Reference**: IMPLEMENTATION_GUIDE.md → Commit 1

**File to modify**: `appinfo/info.xml`

**Task**:
1. Open `appinfo/info.xml`
2. Find line 27 with `<nextcloud min-version="25" max-version="31"/>`
3. Change `max-version="31"` to `max-version="33"`
4. Verify the change

**Exact Change**:
```diff
  <dependencies>
-     <nextcloud min-version="25" max-version="31"/>
+     <nextcloud min-version="25" max-version="33"/>
  </dependencies>
```

**Verification**:
```bash
grep 'max-version' appinfo/info.xml
# Should output: <nextcloud min-version="25" max-version="33"/>
```

**Commit**:
```bash
git add appinfo/info.xml
git commit -m "feat: Update app to support Nextcloud 32 and 33

- Update max-version from 31 to 33 in info.xml
- Allow installation on NC32 and NC33 instances
- Maintains backward compatibility with NC25-31

BREAKING: None
TESTING: Install on NC32 and NC33"
```

---

### Step 2: Create Admin Settings REST API Endpoint (40 minutes)

**Reference**: IMPLEMENTATION_GUIDE.md → Commit 2

**Files to create/modify**:
- `lib/Controller/AdminController.php` (CREATE)
- `appinfo/routes.php` (MODIFY)

**Task 2a: Create AdminController.php**

Create a new file `lib/Controller/AdminController.php` with this exact content:

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
```

**Verification**:
```bash
php -l lib/Controller/AdminController.php
# Should output: No syntax errors detected
```

**Task 2b: Register Routes**

Modify `appinfo/routes.php`:

Find the routes array and add these two lines before the closing bracket:

```php
        // Settings API
        ['name' => 'admin#getSetting', 'url' => '/api/settings/{key}', 'verb' => 'GET'],
        ['name' => 'admin#setSetting', 'url' => '/api/settings/{key}', 'verb' => 'PUT'],
```

**Verification**:
```bash
grep -A 2 "Settings API" appinfo/routes.php
```

**Commit**:
```bash
git add lib/Controller/AdminController.php appinfo/routes.php
git commit -m "feat: Add REST API for admin settings

- Create AdminController with getSetting and setSetting methods
- Implement HTTP endpoints for settings management
- Support both NC32 and NC33 without version checks
- Replace deprecated OCP.AppConfig XML API

Endpoints:
  GET /api/settings/{key} - Get a setting value
  PUT /api/settings/{key} - Set a setting value

BREAKING: None (old API still used by legacy code)
TESTING: 
  - Load settings endpoint with curl
  - Verify values are saved to database
  - Test with admin and non-admin users"
```

---

### Step 3: Migrate Admin.vue to HTTP Settings API (30 minutes)

**Reference**: IMPLEMENTATION_GUIDE.md → Commit 3

**File to modify**: `src/Admin.vue`

**Task**:
1. Open `src/Admin.vue`
2. Locate the `methods` section (around line 240)
3. Find `async updateSetting()` and `async loadSetting()` methods
4. Replace both methods entirely with the new HTTP-based versions

**Replace this** (old code - search for it):
```javascript
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
},
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
},
```

**With this** (new code):
```javascript
async updateSetting(name, value) {
    try {
        const response = await axios.put(
            generateUrl(`/apps/jitsi/api/settings/${name}`),
            { value }
        )
        
        if (!response.data.success) {
            this.errorMessage = this.t('jitsi', 'Failed to save settings')
            console.error('Failed to save setting:', response.data)
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
        const response = await axios.get(
            generateUrl(`/apps/jitsi/api/settings/${name}`),
            { params: { default: defaultValue } }
        )
        
        if (!response.data.success) {
            this.errorMessage = this.t('jitsi', 'Failed to load settings')
            console.error('Failed to load setting:', response.data)
            return defaultValue
        }
        
        return response.data.value || defaultValue
    } catch (e) {
        if (e?.response?.status === 404) {
            // Setting doesn't exist, return default value
            return defaultValue
        }
        
        this.errorMessage = this.t('jitsi', 'Failed to load settings')
        console.error('Failed to load setting:', e)
        throw e
    }
},
```

**Verify imports at top of script block**:
```bash
grep -A 5 "^import" src/Admin.vue | head -15
# Should contain:
# import axios from '@nextcloud/axios'
# import { generateUrl } from '@nextcloud/router'
```

If imports are missing, add them to the top of the script block.

**Verification**:
```bash
npm run lint src/Admin.vue
# Should have no errors related to these methods
```

**Commit**:
```bash
git add src/Admin.vue
git commit -m "feat: Replace deprecated OCP.AppConfig with HTTP API in Admin.vue

- Remove deprecated OCP.AppConfig.getValue/setValue calls
- Use axios to call new /api/settings endpoints
- Migrate from callback-based to async/await pattern
- Improve error handling and user feedback

Breaking: None (backwards compatible with new API)
TESTING:
  - Load admin settings page
  - Update each setting and verify persistence
  - Refresh page and verify settings remain
  - Check browser console for errors
  - Check server logs for errors"
```

---

### Step 4: Install Mitt Event Bus Library (5 minutes)

**Reference**: IMPLEMENTATION_GUIDE.md → Commit 4

**Task**:
```bash
npm install mitt@^3.0.0
```

**Verification**:
```bash
npm list mitt
# Should show: mitt@3.x.x

grep mitt package.json
# Should show: "mitt": "^3.0.0"
```

**Commit**:
```bash
git add package.json package-lock.json
git commit -m "build: Add mitt event bus library for Vue 2/3 compatibility

- Install mitt@^3.0.0 for modern event bus
- Replaces Vue 2 \$root.\$on/\$emit pattern
- Compatible with both Vue 2 and Vue 3
- Better memory management with proper cleanup
- Prevents memory leaks from uncleaned listeners

BREAKING: None (internal refactor)
TESTING: npm install successful, build completes"
```

---

### Step 5: Create Global Event Bus Utility (10 minutes)

**Reference**: IMPLEMENTATION_GUIDE.md → Commit 5

**File to create**: `src/utils/eventBus.js` (new directory + file)

**Task**:
1. Create directory: `src/utils/` (if it doesn't exist)
2. Create file: `src/utils/eventBus.js` with this content:

```javascript
import mitt from 'mitt'

/**
 * Global event bus for component communication
 * Replaces Vue 2's $root.$on/$emit pattern
 * 
 * Usage:
 *   import eventBus from '@/utils/eventBus'
 *   
 *   // Listen for event
 *   eventBus.on('my-event', (data) => { ... })
 *   
 *   // Emit event
 *   eventBus.emit('my-event', data)
 *   
 *   // Listen once
 *   eventBus.once('my-event', (data) => { ... })
 *   
 *   // Unregister listener
 *   eventBus.off('my-event', handler)
 *   
 *   // Clear all listeners
 *   eventBus.all.clear()
 */
const eventBus = mitt()

export default eventBus
```

**Verification**:
```bash
ls -la src/utils/eventBus.js
# Should exist and be readable
```

**Commit**:
```bash
git add src/utils/eventBus.js
git commit -m "feat: Create global event bus utility with mitt

- Create src/utils/eventBus.js singleton
- Provides centralized event communication
- Replaces Vue 2's \$root pattern
- Compatible with future Vue 3 migration

BREAKING: None (not yet used)
TESTING: Import eventBus.js, verify mitt exports"
```

---

### Step 6: Migrate Room.vue to Event Bus (40 minutes)

**Reference**: IMPLEMENTATION_GUIDE.md → Commit 6

**File to modify**: `src/Room.vue`

**This is a complex change with multiple parts. Follow carefully:**

**Task 6a: Add Import**

At the top of the `<script>` block (around line 176-180), add:
```javascript
import eventBus from './utils/eventBus'
```

Make sure it's before other imports.

**Task 6b: Update created() method**

Find the `created()` method (around line 278-320) and find these lines:
```javascript
this.$root.$on('jitsi.device_permission_denied', () => {
    this.permissionDenied = true
})

this.$root.$on('jitsi.system_test_done', () => {
    this.systemTestDone = true
})

this.$root.$on('tol-browser-status', (status) => {
    this.browserStatus = status
})
```

Replace with:
```javascript
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
```

**Task 6c: Add beforeDestroy() hook**

In the `methods` section, add this new method (after the `created()` method or before the first other method):

```javascript
beforeDestroy() {
    // Clean up event listeners to prevent memory leaks
    eventBus.off('jitsi.device_permission_denied', this.onPermissionDenied)
    eventBus.off('jitsi.system_test_done', this.onSystemTestDone)
    eventBus.off('tol-browser-status', this.onBrowserStatus)
    if (this.onMicStopped) {
        eventBus.off('mic-stopped', this.onMicStopped)
    }
    if (this.onCamStopped) {
        eventBus.off('cam-stopped', this.onCamStopped)
    }
    if (this.onStopStreams) {
        eventBus.off('stop-streams', this.onStopStreams)
    }
},
```

**Task 6d: Update stopStreams() method**

Find the `stopStreams()` method (around line 494-504) and replace it:

**Old**:
```javascript
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
```

**New**:
```javascript
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

        eventBus.once('mic-stopped', this.onMicStopped)
        eventBus.once('cam-stopped', this.onCamStopped)

        eventBus.emit('stop-streams')
    })
}
```

**Task 6e: Update joinBrowser() method**

Find the `joinBrowser()` method and locate this code (around line 521):
```javascript
api.addEventListener('readyToClose', () => {
    this.joining = false
    api.dispose()
    this.conferenceRunning = false
    this.conferenceDone = true
    document.getElementById('header').style.display = ''
    this.$root.$emit('resume-preview')
})
```

Replace the last line:
```javascript
api.addEventListener('readyToClose', () => {
    this.joining = false
    api.dispose()
    this.conferenceRunning = false
    this.conferenceDone = true
    document.getElementById('header').style.display = ''
    eventBus.emit('resume-preview')
})
```

**Verification**:
```bash
grep -c "eventBus\." src/Room.vue
# Should be > 10

grep -c "\$root\.\$" src/Room.vue
# Should be 0 (all replaced)

npm run lint src/Room.vue
# Should have no errors
```

**Commit**:
```bash
git add src/Room.vue
git commit -m "feat: Migrate Room.vue from \$root event bus to mitt

- Replace all this.\$root.\$on with eventBus.on
- Replace all this.\$root.\$once with eventBus.once
- Replace all this.\$root.\$emit with eventBus.emit
- Add beforeDestroy() lifecycle hook for cleanup
- Prevent memory leaks from uncleaned listeners
- Improve event management reliability

Affected Events:
  - jitsi.device_permission_denied
  - jitsi.system_test_done
  - tol-browser-status
  - mic-stopped
  - cam-stopped
  - stop-streams
  - resume-preview

BREAKING: None (event names unchanged)
TESTING:
  - Load room page
  - Device selection triggers events correctly
  - Browser status displays correctly
  - System test completes
  - Memory profiling shows no leaks on destroy"
```

---

### Step 7: Migrate BrowserTest.vue to Event Bus (5 minutes)

**Reference**: IMPLEMENTATION_GUIDE.md → Commit 7

**File to modify**: `src/components/BrowserTest.vue`

**Task**:
1. Open `src/components/BrowserTest.vue`
2. At the top of the `<script>` block, add import:
```javascript
import eventBus from '../utils/eventBus'
```

3. Find the `created()` method (around line 89-102)
4. Find this line:
```javascript
this.$root.$emit('tol-browser-status', this.status)
```

5. Replace it with:
```javascript
eventBus.emit('tol-browser-status', this.status)
```

**Verification**:
```bash
grep "eventBus" src/components/BrowserTest.vue
# Should contain: import eventBus and emit call

npm run lint src/components/BrowserTest.vue
# Should have no errors
```

**Commit**:
```bash
git add src/components/BrowserTest.vue
git commit -m "feat: Migrate BrowserTest.vue from \$root to mitt event bus

- Replace this.\$root.\$emit with eventBus.emit
- Consistent event bus architecture across app
- Better testability and maintainability

BREAKING: None (event names unchanged)
TESTING:
  - Load system test page
  - Browser status updates correctly
  - Event emitted to Room.vue listener"
```

---

### Step 8: Build and Finalize (15 minutes)

**Reference**: IMPLEMENTATION_GUIDE.md → Commit 8

**Task**:
```bash
# Run linter with auto-fix
npm run lint:fix

# Build webpack bundles
npm run build

# Verify no errors
ls -lh js/
```

**Verification**:
```bash
# Check bundle sizes
ls -lh js/
# Should see admin.js, index.js, room.js files

# Verify no errors
npm run lint
# Should return clean (no errors)

npm run build
# Should complete with no errors
```

**Commit**:
```bash
git add .
git commit -m "build: Finalize migration with linting and build validation

- Run ESLint to ensure code quality
- Run webpack build to verify bundle compilation
- Confirm no breaking changes to app functionality

BREAKING: None
TESTING: All linting passes, build succeeds"
```

---

## 🧪 Post-Implementation Testing

### Test Phase 1: Build Verification (5 minutes)
```bash
npm run build
npm run lint
# Both should complete with zero errors
```

### Test Phase 2: Manual Testing on NC32 (30 minutes)
- [ ] Uninstall old jitsi app if present
- [ ] Install updated app from this directory
- [ ] Settings page loads without errors
- [ ] Update each setting (server URL, JWT secret, etc.)
- [ ] Settings persist after refresh
- [ ] Create a conference room
- [ ] Access the room and verify system test runs
- [ ] Check browser console for zero errors

### Test Phase 3: Manual Testing on NC33 (30 minutes)
- Repeat all Test Phase 2 steps on NC33 instance

### Test Phase 4: Device Selection (10 minutes)
- [ ] Run system test
- [ ] Select microphone device
- [ ] Select camera device
- [ ] Verify selections registered correctly
- [ ] Check for console errors

### Test Phase 5: Memory Profiling (15 minutes)
- [ ] Open DevTools (Chrome/Firefox)
- [ ] Go to Performance/Memory tab
- [ ] Record memory while navigating to room
- [ ] Navigate away from room
- [ ] Check for memory leaks (should be released)
- [ ] Verify event listener count is low

## ✅ Completion Checklist

- [ ] All 8 commits completed
- [ ] `git log --oneline` shows all 8 commits
- [ ] `npm run lint` passes
- [ ] `npm run build` succeeds
- [ ] Manual testing passes on NC32
- [ ] Manual testing passes on NC33
- [ ] Memory profiling clean
- [ ] Console errors: 0
- [ ] Error logs: 0
- [ ] Ready for code review

## 📊 Git History Check

After completing all commits, verify:
```bash
git log --oneline feat/nc33-support -10

# Should show (in order):
# 8. build: Finalize migration...
# 7. feat: Migrate BrowserTest.vue...
# 6. feat: Migrate Room.vue...
# 5. feat: Create global event bus...
# 4. build: Add mitt event bus library
# 3. feat: Replace deprecated OCP.AppConfig
# 2. feat: Add REST API for admin settings
# 1. feat: Update app to support Nextcloud 32 and 33
```

## 🎯 Success Criteria

When you're done, the implementation is successful if:

✅ All 8 commits are in git history  
✅ No uncommitted changes (`git status` is clean)  
✅ `npm run lint` returns zero errors  
✅ `npm run build` completes successfully  
✅ App installs on NC32 without errors  
✅ App installs on NC33 without errors  
✅ Admin settings load and save correctly  
✅ Device selection works (no console errors)  
✅ System test completes (no console errors)  
✅ Browser profiler shows no memory leaks  
✅ Error logs are empty (both NC32 and NC33)  

## 🚀 Next Steps After Completion

1. Push branch to origin:
```bash
git push origin feat/nc33-support
```

2. Create Pull Request on GitHub
3. Request code review
4. Address any review feedback
5. After approval, merge to main
6. Tag version:
```bash
git tag v0.20.0
git push origin v0.20.0
```

7. Build and submit to App Store

---

## 📞 Reference Documents

If you get stuck, consult these documents in this order:

1. **IMPLEMENTATION_GUIDE.md** - Exact specifications for current step
2. **NEXTCLOUD_33_MIGRATION_PLAN.md** - Detailed explanation and alternatives
3. **QUICK_REFERENCE.md** - Troubleshooting Q&A
4. **TECHNICAL_FIX_GUIDE.md** - Code solution examples

---

## ⚠️ Important Notes

- **Do NOT** skip any steps - they must be done in order
- **Test after each commit** - don't wait until the end
- **Commit messages must be exact** - they document the migration
- **Follow diffs precisely** - exact spacing and formatting matter
- **Git history is critical** - this becomes your release documentation

---

**Good luck with the implementation! Follow these steps precisely and you'll have a production-ready NC32/NC33 compatible Jitsi app.**
