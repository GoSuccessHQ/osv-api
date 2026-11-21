# Fixtures

Real responses of api.osv.dev, recorded on 2026-11-21 with read-only requests
and only pretty-printed:

- `querybatch.json`: `POST /v1/querybatch` for `openssh 1:9.6p1-3ubuntu13.20`
  (`Ubuntu:24.04:LTS`), `stdlib 1.26.4` (`Go`) and
  `github.com/restic/restic 0.19.1` (`Go`), in that order.
- `UBUNTU-CVE-2026-119618.json`: Apache mod_http2, fixed in 24.04 by
  `2.4.58-1ubuntu8.19`, Ubuntu priority `high`, CVSS 3.1 vector (8.1).
- `UBUNTU-CVE-2026-110928.json`: OpenSSH, no fix in 24.04 yet, Ubuntu
  priority `medium`, no CVSS vector.
- `GO-2026-4970.json`: Go standard library, with aliases and a SEMVER range,
  no severity.
