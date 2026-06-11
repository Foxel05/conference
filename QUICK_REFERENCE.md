# Nextcloud Jitsi - Quick Reference & Checklists

**Last Updated**: June 11, 2026  
**Status**: Ready for Implementation  
**Version**: v0.19.0 → v0.20.0

---

## 📋 At a Glance

| Item | Value |
|------|-------|
| **Current Status** | Supports NC25-31 (blockedNC32/33) |
| **Target Status** | Supports NC25-33 |
| **Approach** | Single codebase, no forking |
| **Total Effort** | 70-100 developer hours |
| **Timeline** | 4-5 weeks |
| **Risk Level** | Medium (manageable) |
| **Breaking Changes** | None |

---

## 🔴 CRITICAL ISSUES (Must Fix)

### Issue #1: Version Constraint
```
File: appinfo/info.xml:27
Current: <nextcloud min-version="25" max-version="31"/>
Fix: Change max-version to 33
Time: 5 minutes
```

### Issue #2: Deprecated Settings API
```
File: src/Admin.vue:244-268
Problem: OCP.AppConfig removed in NC33
Solution: Create HTTP API endpoint + axios calls
Time: 90 minutes
```

---

## 🟠 HIGH PRIORITY ISSUES (Do ASAP)

| # | Issue | File | Fix | Time |
|---|-------|------|-----|------|
| 3 | Event bus pattern ($root) | Room.vue, BrowserTest.vue | Replace with mitt | 90 min |
| 4 | Missing cleanup | Room.vue | Add beforeDestroy() | 10 min |
| 5 | CSP/FeaturePolicy APIs | PageController.php | Verify in NC33 | 45 min |
| 6 | Avatar route | UserController.php | Verify in NC33 | 30 min |
| 7 | Settings interface | AdminSettings.php | Verify ISettings | 30 min |
| 8 | QueryBuilder API | RoomMapper.php | Verify API | 30 min |

---

## 🟡 MEDIUM PRIORITY ISSUES (Should Do)

| # | Issue | File | Fix | Time |
|---|-------|------|-----|------|
| 9 | localStorage sync | Room.vue | Use AppConfig API | 60 min |
| 10 | Custom CSS | Admin.vue | Use @nextcloud/vue | 90 min |
| 11 | Legacy navigation | info.xml | Plan migration | 60 min |

---

## 🟢 LOW PRIORITY ISSUES (Can Wait)

| # | Issue | File | Fix | Time |
|---|-------|------|-----|------|
| 12-15 | Component updates | Various | Verify | 30 min each |

---

## ✅ Implementation Checklist

### Phase 1: Critical Fixes (Week 1)

**Monday**:
- [ ] Create AdminController.php
- [ ] Add routes to routes.php
- [ ] Test endpoints with curl

**Tuesday**:
- [ ] Update Admin.vue methods
- [ ] Test settings load/save
- [ ] Verify no errors in console

**Wednesday**:
- [ ] `npm install mitt@^3.0.0`
- [ ] Create src/utils/eventBus.js
- [ ] Verify build succeeds

**Thursday**:
- [ ] Update Room.vue with eventBus
- [ ] Update BrowserTest.vue with eventBus
- [ ] Add beforeDestroy() cleanup
- [ ] Test device selection

**Friday**:
- [ ] Run full test suite
- [ ] Test on NC32 instance
- [ ] Test on NC33 instance

### Phase 2: High Priority (Week 2)

**Monday-Tuesday**:
- [ ] Verify CSP/FeaturePolicy APIs
- [ ] Verify Avatar route exists
- [ ] Verify AdminSettings interface
- [ ] Verify QueryBuilder API

**Wednesday**:
- [ ] Fix any compatibility issues
- [ ] Run full test suite

**Thursday-Friday**:
- [ ] Comprehensive functional testing
- [ ] Memory leak testing
- [ ] Performance benchmarking

### Phase 3: Medium Priority (Week 3)

- [ ] Implement user preferences API
- [ ] Replace custom CSS with components
- [ ] Plan navigation migration

### Phase 4: QA & Release (Week 4)

- [ ] Full test matrix
- [ ] Documentation complete
- [ ] Release notes ready
- [ ] App Store submission

---

## 🔧 Code Changes Summary

### New Files
```
+ lib/Controller/AdminController.php (150 lines)
+ src/utils/eventBus.js (20 lines)
```

### Modified Files
```
~ appinfo/info.xml (1 line)
~ appinfo/routes.php (3 lines)
~ src/Admin.vue (40 lines)
~ src/Room.vue (80 lines)
~ src/components/BrowserTest.vue (5 lines)
~ package.json (1 line)
```

### Total Delta: ~300 lines

---

## 📦 Dependencies to Add

```json
{
  "npm": {
    "mitt": "^3.0.0"
  },
  "composer": {}
}
```

---

## 🔍 Verification Commands

### Frontend
```bash
npm run lint:fix        # Fix linting issues
npm run build           # Build webpack bundles
npm test                # Run tests (if available)
```

### Backend
```bash
php -l lib/Controller/AdminController.php
composer validate
```

### Deployment
```bash
# Package app
tar czf jitsi-0.20.0.tar.gz \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='build' \
  .
```

---

## 🧪 Testing Matrix

### Environment Combinations
```
NC32 + PHP 7.4   ✅ Test
NC32 + PHP 8.0   ✅ Test
NC32 + PHP 8.1   ✅ Test
NC32 + PHP 8.2   ✅ Test
NC33 + PHP 8.0   ✅ Test
NC33 + PHP 8.1   ✅ Test
NC33 + PHP 8.2   ✅ Test
NC33 + PHP 8.3   ✅ Test
```

### Functional Test Cases
```
1. Admin Settings
   - [ ] Load settings page (no errors)
   - [ ] Update each setting
   - [ ] Save settings
   - [ ] Refresh page
   - [ ] Verify settings persist

2. Room Access
   - [ ] Create conference room
   - [ ] Access room page
   - [ ] Run system test
   - [ ] Check browser detection
   - [ ] Select devices

3. Conference
   - [ ] Join Jitsi conference
   - [ ] Share screen
   - [ ] Use chat
   - [ ] Leave conference

4. Error Handling
   - [ ] No console errors
   - [ ] No PHP error logs
   - [ ] Graceful error messages
```

---

## 🚀 Git Workflow

### Create Feature Branch
```bash
git checkout -b feat/nc33-support
```

### 8 Commits Required

1. `feat: Update app to support Nextcloud 32 and 33`
2. `feat: Add REST API for admin settings`
3. `feat: Replace deprecated OCP.AppConfig with HTTP API`
4. `build: Add mitt event bus library`
5. `feat: Create global event bus utility`
6. `feat: Migrate Room.vue from $root to mitt`
7. `feat: Migrate BrowserTest.vue to event bus`
8. `build: Finalize migration with validation`

### Push and Create PR
```bash
git push origin feat/nc33-support
# Create Pull Request on GitHub
```

---

## 📚 Key Files to Read

### Audit Documents
- [NEXTCLOUD_33_MIGRATION_PLAN.md](NEXTCLOUD_33_MIGRATION_PLAN.md) - **START HERE**
- [EXECUTIVE_SUMMARY.md](EXECUTIVE_SUMMARY.md) - **High-level overview**
- [ISSUES_INDEX.md](ISSUES_INDEX.md) - Quick reference table
- [NC32_NC33_COMPATIBILITY_AUDIT.md](NC32_NC33_COMPATIBILITY_AUDIT.md) - Detailed findings

### Implementation Guides
- [IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md) - **Step-by-step commits**
- [TECHNICAL_FIX_GUIDE.md](TECHNICAL_FIX_GUIDE.md) - Code solutions

---

## ⚡ Quick Start for Developers

### Day 1: Setup
```bash
# Clone and prepare
git clone https://github.com/nextcloud/jitsi.git
cd jitsi
npm install
npm install mitt@^3.0.0

# Verify current state
npm run lint
npm run build
```

### Day 2-3: Implement Critical Fixes
```bash
# Follow IMPLEMENTATION_GUIDE.md commits 1-3
# - Update version constraint
# - Create AdminController
# - Update Admin.vue
```

### Day 4-5: Event Bus Migration
```bash
# Follow IMPLEMENTATION_GUIDE.md commits 4-7
# - Install mitt
# - Create eventBus utility
# - Update Room.vue
# - Update BrowserTest.vue
```

### Day 6-7: Testing
```bash
# Test on both NC32 and NC33 instances
# - Admin settings load/save
# - Device selection works
# - System test runs
# - No console errors
```

### Day 8: Submit
```bash
# Push branch and create PR
git push origin feat/nc33-support
# Request code review
# Merge after approval
```

---

## 🆘 Troubleshooting Quick Answers

### "Settings not loading"
**Check**: Is HTTP endpoint registered in routes.php?  
**Fix**: Verify `admin#getSetting` route and controller exist

### "Device selection not working"
**Check**: Is eventBus imported in Room.vue?  
**Fix**: Import mitt eventBus, not $root

### "Events not firing"
**Check**: Are listeners registered with eventBus.on()?  
**Fix**: Verify event names match between emitter and listener

### "Memory leaks detected"
**Check**: Is beforeDestroy() in Room.vue?  
**Fix**: Add beforeDestroy() with eventBus.off() for all listeners

### "Build fails"
**Check**: Is mitt installed? `npm list mitt`  
**Fix**: Run `npm install mitt@^3.0.0`

### "Linting errors"
**Fix**: Run `npm run lint:fix`

---

## 📊 Success Metrics

After implementation, verify:

- ✅ `npm run lint` - No errors
- ✅ `npm run build` - Completes successfully
- ✅ App installs on NC32 - No errors
- ✅ App installs on NC33 - No errors
- ✅ Settings load correctly - Both versions
- ✅ Settings save correctly - Both versions
- ✅ Device selection works - Both versions
- ✅ No JavaScript console errors - Both versions
- ✅ No PHP error logs - Both versions
- ✅ Memory profiling clean - Both versions

---

## 🎯 Version Release Plan

### v0.20.0 (Current Effort)
- ✅ NC32 support
- ✅ NC33 support
- ✅ HTTP settings API
- ✅ Event bus migration
- ⏳ User preference sync (optional for 0.21.0)
- ⏳ UI component updates (optional for 0.21.0)

### v0.21.0 (Future)
- ⏳ User preferences synced per-device
- ⏳ @nextcloud/vue component styling
- ⏳ Vue 3 migration planning

### v1.0.0 (Roadmap)
- ⏳ Vue 3 migration
- ⏳ TypeScript conversion
- ⏳ Modern architecture

---

## 📞 Support Contacts

### For Nextcloud API Questions
- Nextcloud Developer Forum: https://help.nextcloud.com/c/development/11
- Nextcloud GitHub Discussions: https://github.com/nextcloud/server/discussions

### For Mitt Library Questions
- Mitt GitHub: https://github.com/developit/mitt
- Mitt Docs: https://github.com/developit/mitt#readme

### For App Testing
- Set up test instances or request resources from Nextcloud community

---

## 📝 Checklist Before Release

- [ ] All 8 commits implemented
- [ ] Code reviewed and approved
- [ ] All linting passes
- [ ] All tests pass
- [ ] Tested on NC32 (multiple PHP versions)
- [ ] Tested on NC33 (multiple PHP versions)
- [ ] No console errors
- [ ] No error logs
- [ ] Memory leak testing passed
- [ ] Documentation updated
- [ ] Release notes prepared
- [ ] Version bumped to 0.20.0
- [ ] CHANGELOG updated
- [ ] Tag created: v0.20.0
- [ ] App Store submission ready

---

**Document Version**: 1.0  
**Last Updated**: June 11, 2026  
**Audience**: Developers, QA, Release Managers
