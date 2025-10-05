# Outstanding Work

## Issues to Fix

### File Explorer SSH Error
**Status:** Not investigated yet

When navigating to File Explorer, getting this error:
```
Warning: Permanently added '192.168.1.59' (ED25519) to the list of known hosts.
PHP Parse error: syntax error, unexpected token "=", expecting end of file in Command line code on line 2
```

**Context:**
- No application setup or connected with Git yet
- Error occurs during file navigation
- Appears to be a PHP syntax error in SSH command execution

**Notes:**
- Likely related to SSH command execution in file explorer functionality
- May be malformed PHP code being passed to remote server
- Could be issue with escaping or command formatting in SSH execution

---

### GitHub OAuth 404 Error
**Status:** Not fixed yet

When connecting to GitHub in Source Providers card, getting 404 on OAuth authorization:
```
https://github.com/login/oauth/authorize?redirect_uri=http%3A%2F%2F192.168.1.51%3A8080%2Fsource-providers%2Fgithub%2Fcallback&scope=user%3Aemail%2Crepo%2Cadmin%3Arepo_hook&response_type=code&state=GzOTzFjxmXXANAukpLpnhyyyBpHu2eitHJb88lJT
```

**Likely Cause:**
- GitHub OAuth App doesn't have `http://192.168.1.51:8080/source-providers/github/callback` registered as authorized callback URL
- Missing `GITHUB_CLIENT_ID` and `GITHUB_CLIENT_SECRET` in `.env` file

**Fix Required:**
1. Create GitHub OAuth App at https://github.com/settings/developers
2. Register callback URL: `http://192.168.1.51:8080/source-providers/github/callback`
3. Add credentials to `.env`:
   ```
   GITHUB_CLIENT_ID=your_client_id
   GITHUB_CLIENT_SECRET=your_client_secret
   ```
4. Restart dev server: `composer dev`

---

## Component Unification Task

### Error Display Components
**Status:** To Do

**Issue:** Multiple error display patterns across the codebase causing duplication

**Current Implementations:**
1. **Git Repository Error** (`site-git-repository.tsx:168-187`):
   - Uses Alert component with destructive variant
   - Shows error title and expandable details
   - Has error log display in `<details>` tag with scrollable `<pre>` block

2. **Database Operations** (`database-modern.tsx`):
   - Uses window.confirm() for deletion
   - Different feedback mechanism (likely toast-based from other parts)

**Goal:** Create unified error/success feedback component that can be reused across:
- Database installation/update/deletion
- Git repository operations
- Other package operations

**Requirements:**
- Support for error title and expandable error logs
- Success/info/warning/error variants
- Consistent styling with current Alert component
- Reusable across all server/site operations

**Files to Review:**
- `resources/js/pages/servers/site-git-repository.tsx` (current error UI)
- `resources/js/components/database/database-status-display.tsx` (database operations)
- Any existing toast/notification components

---

## Feature Implementations

### Custom Git Provider (Update Git Remote)
**Status:** Not implemented
**Priority:** Medium

**Issue:** "Custom" provider button in "Update Git Remote" card does nothing when clicked

**Location:** `resources/js/pages/servers/site-git-repository.tsx:248-254`
- Currently just a `<button>` with no functionality
- GitHub button also has no functionality
- No form submission logic for updating git remote

**Requirements:**
1. Implement Custom provider selection logic
2. Handle custom Git URLs (not just GitHub format)
3. Add form submission for "Update Git Remote" button (line 271)
4. Backend endpoint to handle git remote updates
5. Validation for custom Git URLs (SSH format, HTTPS, etc.)

**Related:**
- May need new controller method or update existing one
- Should support various Git hosting providers (GitLab, Bitbucket, self-hosted)

---
