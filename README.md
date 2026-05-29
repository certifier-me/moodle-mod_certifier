# Certifier - Certificates & Badges (`mod_certifier`)

Moodle activity module that issues Certifier credentials (certificate/badge) when learners meet configured completion rules.

## Requirements

- Moodle **4.1+** or above
- Certifier account with API access
- Certifier API key configured by an admin
- Moodle cron must run normally, otherwise queued issuances will not be processed.

## Install

1. Upload plugin ZIP in Moodle (`Site administration -> Plugins -> Install plugins`), or place folder as `mod/certifier`.
2. Complete Moodle upgrade.
3. Configure plugin settings:
   - `mod_certifier/apiurl`
   - `mod_certifier/apikey`
   - `mod_certifier/issuerportaldomain` (optional, for custom domain, defaults to `https://credsverse.com`)

## Basic usage

1. In a course, add **Certifier** activity.
2. Choose Certifier credential template.
3. Choose trigger:
   - Course completion, or
   - Selected activity completion (optional minimum grade).
4. Choose delivery mode (create / issue / send).

Issuance is queued and processed via Moodle ad hoc tasks.

## Backup and restore

- Activity configuration is included in normal Moodle course backup/restore.
- Per-user issuance history is included when the Moodle backup includes user data.
- Restored in-flight queue rows are preserved as history but are not resumed automatically, to avoid duplicate Certifier API calls after restore.
