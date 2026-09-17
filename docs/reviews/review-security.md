# Security Review

Review vulnerabilities, authentication, authorization, untrusted input, sensitive data exposure, secret handling, and insecure configuration.

Security findings must be based on concrete risk.

Do not duplicate domain-specific business rules in this document.

## Review priorities

Review in this order:

1. authentication;
2. authorization;
3. untrusted input;
4. sensitive data exposure;
5. secrets and credentials;
6. injection;
7. session and CSRF protection;
8. browser security;
9. configuration;
10. dependencies and external integrations.

Authentication does not replace authorization.

Everything received from the browser or an external request must be treated as untrusted.

## General checks

Look for:

- hardcoded credentials;
- committed secrets;
- sensitive values in logs;
- internal implementation details exposed to users;
- trust in client-provided identity or permissions;
- missing validation at trust boundaries;
- missing authorization;
- horizontal or vertical privilege escalation;
- client-provided identifiers treated as proof of ownership;
- insecure password handling;
- insecure session handling;
- SQL injection;
- XSS;
- CSRF where applicable;
- open redirects;
- unsafe file upload;
- path traversal;
- mass assignment;
- insecure file handling;
- insecure production configuration;
- sensitive information included in unnecessary responses.

## PHP / Laravel

Check:

- protected routes use the expected authentication middleware;
- resource-level authorization is enforced when required;
- authenticated identity comes from the trusted authentication mechanism;
- IDs or roles sent by the client are not treated as proof of ownership or permission;
- `$request->all()` is not used carelessly in sensitive operations;
- mass assignment is controlled;
- raw SQL does not interpolate external input;
- passwords use the framework-approved hashing mechanism;
- tokens and session identifiers are not exposed unnecessarily;
- `.env` is not committed;
- `APP_DEBUG` is not enabled in production configuration;
- stack traces and internal exceptions are not exposed publicly;
- cookie/session settings are appropriate when relevant;
- CSRF protection remains active for session-based flows where required;
- uploaded files are validated appropriately;
- client-controlled file names or paths cannot cause unsafe access;
- redirects based on external input are validated;
- sensitive model attributes are not serialized unnecessarily;
- secrets are not committed in configuration;
- logs do not contain passwords, cookies, session IDs, authorization headers, tokens, or unnecessary PII.

## React / TypeScript

Assume all browser code and browser-visible configuration are public.

Check:

- secrets are not placed in frontend environment variables or bundles;
- sensitive tokens are not persisted insecurely without a documented reason;
- sensitive information is not stored unnecessarily in `localStorage` or `sessionStorage`;
- `dangerouslySetInnerHTML` is not used with untrusted content;
- externally supplied HTML is sanitized when rendering is actually required;
- user-controlled URLs are not used unsafely;
- redirects cannot be manipulated into an open redirect;
- sensitive information is not logged to the browser console;
- hiding UI controls is not treated as authorization;
- critical decisions are not enforced only in frontend state;
- client-controlled values are not trusted as security decisions;
- the frontend does not expose more backend data than necessary.

Frontend variables are public once they are shipped to the browser.

Never treat a secret included in a frontend bundle as secret.

## PHP ↔ React / API boundary

Check:

- backend does not trust roles sent by React;
- backend does not trust user IDs sent by React as authenticated identity;
- validation does not exist only in React;
- sensitive fields are not returned just because the frontend ignores them;
- HTTP errors do not expose stack traces, SQL, file paths, or internal implementation details;
- resource modification performs real backend authorization;
- request payloads cannot alter arbitrary model fields;
- contract changes do not accidentally expose new sensitive information.

## Injection and data handling

Review:

- SQL construction;
- HTML rendering;
- URL construction;
- redirect targets;
- file names and paths;
- serialized output;
- query parameters used in dynamic operations.

Prefer parameterized queries, framework escaping, validated redirects, and explicit allowlists where appropriate.

## Dependencies

Report:

- unnecessary security-sensitive dependency;
- clearly abandoned or inappropriate package when it creates real risk;
- disabling framework protections without justification;
- custom security implementation replacing a safer framework capability without need.

Do not fail a review solely because a dependency version looks old without verifying the actual project context and risk.

## Blockers

```text
❌ Blocker
```

Examples:

- plaintext password storage;
- exploitable SQL injection;
- exploitable XSS;
- real secret committed to the repository;
- `.env` committed with sensitive values;
- missing authentication on a surface that requires it;
- missing authorization allowing unauthorized access;
- secret shipped in the frontend bundle;
- unsafe file handling allowing unauthorized access or execution;
- destructive operation exposed without required protection.

## High severity

```text
⚠️ High
```

Examples:

- unnecessary sensitive data exposure;
- relevant mass assignment risk;
- critical validation enforced only in frontend code;
- public stack traces;
- sensitive token stored inappropriately;
- incomplete resource authorization;
- insecure configuration with concrete impact.

## Review output

For each finding, report:

- affected file or section;
- vulnerability;
- exploitation or failure scenario;
- impact;
- recommended correction.

Do not label something a vulnerability without explaining how it can be exploited or which security property it violates.

When no relevant security issue is found, state that clearly and mention only what could not be verified.
