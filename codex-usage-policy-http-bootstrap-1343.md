# codex #1343 — cold CLI HTTP bootstrap

The owned DEV 5.1.8 runtime completed its normal Minor update successfully. Its
next read-only user-policy check exposed a missing HTTP dependency: the public
usage-policy SDK constructs global `curl`, but Moodle's normal configuration
bootstrap does not define that legacy class. It lives in `lib/filelib.php`.
The same failure occurred with normal cron user/renderer setup. No local policy
file, user, password, role or activity timestamp had been changed by that check.

The private HTTP-client factory now requires Moodle's file library before
constructing the real client. The existing PHPUnit transport injection remains
first, and signing/credential/body/URL/timeout rules are unchanged. Plugin source
version 2026101601 (release 0.2.3) identifies this dependency repair.

The causal regression launches a fresh PHP process with real Moodle config-only
bootstrap and moodlelib. It verifies global curl is initially absent and invokes
the actual HTTP factory twice. No fake curl implementation, DB connection or
network request is used. Old source fails with Class curl not found; repaired
source constructs the real Moodle class twice. The existing full API tests cover
signed policy calls and preserve the request/transport behavior.

This source fix must propagate through normal KTS CI and a reviewed runtime
installation. A library preload in an operator script does not prove the normal
scheduled task is fixed. Do not directly patch kts_moodle or label a fabricated
Moodle point version. Record the actual resulting source/runtime publication and
verify a normal cold scheduled task on the owned fixture before counting/header
acceptance. Production catalogue/defaults and fleet activation are separate.

Local verification on Moodle 5.1.8 / PHP 8.3: all 100 registration tests
pass (398 assertions). The combined registration and usage suite passes all 131
tests (481 assertions); its six existing usage-task output warnings remain
reported as risky tests. The suite also reports 92 existing PHPUnit annotation
deprecations. PHP lint, Moodle Code Checker (zero warnings), Moodle PHPDoc
Checker, validation and savepoint checks pass. Hosted matrix results are recorded
in the source PR.
