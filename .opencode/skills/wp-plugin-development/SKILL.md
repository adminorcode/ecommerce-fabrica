---
name: wp-plugin-development
description: "Use when developing WordPress plugins: architecture and hooks, activation/deactivation/uninstall, admin UI and Settings API, data storage, cron/tasks, security (nonces/capabilities/sanitization/escaping), and generic release artifacts. Do not use for HostGator/cPanel packaging of petshop-core — that is preparar-deploy."
compatibility: "Targets WordPress 7.0+ (PHP 7.4.0+). Filesystem-based agent with bash + node. Some workflows require WP-CLI."
---

# WP Plugin Development

## When to use

Use this skill for plugin work such as:

- creating or refactoring plugin structure (bootstrap, includes, namespaces/classes)
- adding hooks/actions/filters
- activation/deactivation/uninstall behavior and migrations
- adding settings pages / options / admin UI (Settings API)
- security fixes (nonces, capabilities, sanitization/escaping, SQL safety)
- packaging generic release artifacts (readme, built assets). **Not** HostGator/cPanel of this store — use `preparar-deploy` / `npm run prepare:deploy`

## Inputs required

- Repo root + target plugin(s) (path to plugin main file if known).
- Where this plugin runs: single site vs multisite; WP.com conventions if applicable.
- Target WordPress + PHP versions (affects available APIs and placeholder support in `$wpdb->prepare()`).

## Procedure

### 0) Triage and locate plugin entrypoints

1. Run triage:
   - `node .agents/skills/wp-project-triage/scripts/detect_wp_project.mjs`
2. Detect plugin headers (deterministic scan):
   - `node .agents/skills/wp-plugin-development/scripts/detect_plugins.mjs`

If this is a full site repo, pick the specific plugin under `wp-content/plugins/` or `mu-plugins/` before changing code.

### 1) Follow a predictable architecture

Guidelines:

- Keep a single bootstrap (main plugin file with header).
- Avoid heavy side effects at file load time; load on hooks.
- Prefer a dedicated loader/class to register hooks.
- Keep admin-only code behind `is_admin()` (or admin hooks) to reduce frontend overhead.

See:
- `.agents/skills/wp-plugin-development/references/structure.md`

### 2) Hooks and lifecycle (activation/deactivation/uninstall)

Activation hooks are fragile; follow guardrails:

- register activation/deactivation hooks at top-level, not inside other hooks
- flush rewrite rules only when needed and only after registering CPTs/rules
- uninstall should be explicit and safe (`uninstall.php` or `register_uninstall_hook`)

See:
- `.agents/skills/wp-plugin-development/references/lifecycle.md`

### 3) Settings and admin UI (Settings API)

Prefer Settings API for options:

- `register_setting()`, `add_settings_section()`, `add_settings_field()`
- sanitize via `sanitize_callback`

See:
- `.agents/skills/wp-plugin-development/references/settings-api.md`

### 4) Security baseline (always)

Before shipping:

- Validate/sanitize input early; escape output late.
- Use nonces to prevent CSRF *and* capability checks for authorization.
- Avoid directly trusting `$_POST` / `$_GET`; use `wp_unslash()` and specific keys.
- Use `$wpdb->prepare()` for SQL; avoid building SQL with string concatenation.

See:
- `.agents/skills/wp-plugin-development/references/security.md`

### 5) Data storage, cron, migrations (if needed)

- Prefer options for small config; custom tables only if necessary.
- For cron tasks, ensure idempotency and provide manual run paths (WP-CLI or admin).
- For schema changes, write upgrade routines and store schema version.

See:
- `.agents/skills/wp-plugin-development/references/data-and-cron.md`

### 6) HostGator packaging (this repository)

`petshop-core.php` loads `vendor/autoload.php` on every request. PHPUnit lives in Composer `require-dev`. Shipping the worktree `vendor/` or deleting `myclabs`/`phpunit` without regenerating autoload fatals production (`deep_copy.php`).

- Package only via `preparar-deploy` (`npm run prepare:deploy`).
- The script must `composer dump-autoload --no-dev --optimize` on the **staged** plugin, then fail if autoload still mentions `myclabs`, `phpunit/phpunit`, or `deep-copy`.
- Never run `dump-autoload --no-dev` on the worktree plugin (breaks local PHPUnit).
- Do not chmod leftover `vendor/myclabs` on the server as a “fix”.

## Verification

- Plugin activates with no fatals/notices.
- Settings save and read correctly (capability + nonce enforced).
- Uninstall removes intended data (and nothing else).
- Run repo lint/tests (PHPUnit/PHPCS if present) and any JS build steps if the plugin ships assets.

## Failure modes / debugging

- Activation hook not firing:
  - hook registered incorrectly (not in main file scope), wrong main file path, or plugin is network-activated
- Settings not saving:
  - settings not registered, wrong option group, missing capability, nonce failure
- Security regressions:
  - nonce present but missing capability checks; or sanitized input not escaped on output
- Production fatal `deep_copy.php` / `myclabs` / `Permission denied` under `vendor/`:
  - Composer autoload was generated with PHPUnit (`require-dev`) and then `myclabs` was deleted. Re-package with `preparar-deploy`. Do not chmod leftovers.

See:
- `.agents/skills/wp-plugin-development/references/debugging.md`

## Escalation

For canonical detail, consult the Plugin Handbook and security guidelines before inventing patterns.

Base directory for this skill: .agents/skills/wp-plugin-development
Relative paths in this skill (e.g., scripts/, reference/) are relative to this base directory.
