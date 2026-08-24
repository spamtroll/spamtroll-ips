# Suite facts

Behaviour of the IPS Community Suite that this application depends on, with the
evidence for each answer.

**Reference Suite version: 4.7.22.** Verified against a licensed on-disk copy of
the Suite belonging to the project owner. This file records *findings only* —
never Suite source code, which is proprietary.

**Rule:** every `[?]` marker in this repository's code or docs must point at a row
in this table. A row is either answered, or explicitly marked
`REQUIRES TEST INSTALL`. Nothing in between.

**Method legend**

| Method | Meaning |
|---|---|
| `read` | Read of the Suite source file named in the evidence column |
| `install` | Requires a running Suite; not answerable from source |

---

## Answered

| # | Question | Answer | Suite | Method | Evidence | Date |
|---|---|---|---|---|---|---|
| U1 | Where does the installer read hooks from? | `data/hooks.json`. `data/build.xml` has no reader anywhere in the Suite — it is only ever written. Archive validation reads `phar://…/data/application.json` | 4.7.22 | read | `system/Application/Application.php:2528-2559` (`installHooks()`: `DELETE FROM core_hooks WHERE app=?`, insert per entry, `\IPS\Plugin\Hook::writeDataFile()`) | 2026-08-24 |
| U2 | Format of `data/extensions.json`? | A map `name → fully-qualified class name`, iterated with `foreach ($json[$app][$extension] as $name => $classname)`. An empty object means **zero** extensions load | 4.7.22 | read | `system/Application/Application.php:712`; shape confirmed against `applications/core/data/extensions.json` (`"System" => "IPS\\core\\extensions\\core\\MemberSync\\System"`) | 2026-08-24 |
| U2b | Which methods does the Suite call on an `Uninstall` extension? | `preUninstall($appDirectory)` and `postUninstall($appDirectory)`, both guarded by `method_exists`. There is **no** `onUninstall()` — the plan's prose naming it was wrong; this repo's extension already used the correct names | 4.7.22 | read | `system/Application/Application.php:4598-4610` (pre), `:4971-4977` (post) | 2026-08-24 |
| U2c | How are `MemberSync` extensions dispatched? | `\IPS\Application::allExtensions('core', 'MemberSync', FALSE)`, then `method_exists($class, $method)` before `$class->$method($member, …)`. Skipped entirely while the setup dispatcher is active | 4.7.22 | read | `system/Member/Member.php:4285-4300` | 2026-08-24 |
| U3 | Signature of `\IPS\Content\Comment::create()`? | `create($item, $comment, $first=FALSE, $guestName=NULL, $incrementPostCount=NULL, $member=NULL, \IPS\DateTime $time=NULL, $ipAddress=NULL, $hiddenStatus=NULL, $anonymous=NULL)` — matches `hooks/Comment.php` character for character | 4.7.22 | read | `system/Content/Comment.php:68` | 2026-08-24 |
| U4a | Signature of `\IPS\Member::spamService()`? | `spamService($type='register', $emailAddress=NULL, &$spamCode=NULL, &$disposable=FALSE, &$geoBlock=FALSE)` — matches `hooks/Member.php` character for character | 4.7.22 | read | `system/Member/Member.php:4038` | 2026-08-24 |
| U4b | Is `spamService()` gated on `spam_service_enabled`? | **Yes.** With IPS spam defence switched off the method is never called, so the registration hook never runs, while the app's own AdminCP still reports itself enabled | 4.7.22 | read | `applications/core/modules/front/system/register.php:564-566`; same gate at `:916-918` | 2026-08-24 |
| U4c | What do the `spamService()` return codes mean, and who acts on them? | `1` proceed, `2` `reg_auth_type='admin'`, `3` `temp_ban=-1` + `bw_is_spammer`, `4` deny, `5` `mod_posts`. The side effects for 2/3/5 are performed **inside `spamService()` itself**, driven by the `spam_service_action_{$spamCode}` setting. The caller reacts to `== 4` and nothing else | 4.7.22 | read | `system/Member/Member.php:4086-4123` (switch); `applications/core/modules/front/system/register.php:567-570` (caller) | 2026-08-24 |
| U4d | Is the member saved after `spamService()` during registration? | **Yes** — `$member->save()` runs after the spam-service block, so a property set from inside the hook (e.g. `mod_posts`) is persisted | 4.7.22 | read | `applications/core/modules/front/system/register.php:574-576` | 2026-08-24 |
| U5 | What does `\IPS\Http\Url::external()` throw? | Nothing. It is a one-line `return new static($url, TRUE)`. There is no `\IPS\Http\Url\Exception` class in the Suite | 4.7.22 | read | `system/Http/Url.php:174-177` | 2026-08-24 |
| U6a | Does `\IPS\Http\Response` expose response headers? | **Yes** — `public $httpHeaders`, populated by the response parser as `name => value` with the case the server sent | 4.7.22 | read | `system/Http/Response.php:43`, filled at `:118` | 2026-08-24 |
| U6b | Does the Suite follow redirects by default? | **Yes, up to 5.** `request()` defaults `$followRedirects=5`, so a 30x sends the request — including `X-API-Key` — to whatever host the `Location` names | 4.7.22 | read | `system/Http/Url.php:573` | 2026-08-24 |
| U6c | How is redirect-following switched off? | Pass `0` (or `FALSE`) as the third argument to `request()`. Both transports guard with a plain truthiness test, so `0` disables it | 4.7.22 | read | `system/Http/Request/Curl.php:499`; `system/Http/Request/Sockets.php:276` | 2026-08-24 |
| U8 | Does `Content::hide()` propagate to the item when the comment is the first post? | **No.** It sets the hidden/approved column on `$this`, saves, and never touches `item()`. Hiding the first post therefore leaves the topic visible. It also throws `\RuntimeException` when the class maps neither a `hidden` nor an `approved` column | 4.7.22 | read | `system/Content/Content.php:1355-1412`; throw at `:1366`. `Item::hide()` is a separate method | 2026-08-24 |
| U8b | What does the `$member` argument of `hide()` mean? | `NULL` = the currently logged-in member, `FALSE` = no member (`sdl_obj_member_id = 0`). Passing `NULL` from a hook credits the hide to the poster — i.e. the spammer | 4.7.22 | read | `system/Content/Content.php:1351` (docblock) and `:1387` (`$member === FALSE ? 0 : …`) | 2026-08-24 |
| U9 | Where do cron tasks come from? | `data/tasks.json`, a map `taskKey → frequency`. Not from `build.xml` | 4.7.22 | read | `system/Application/Application.php:1748-1750`, `:4272-4274` | 2026-08-24 |
| U10 | Is `Settings::changeValues()` a no-op for a key absent from `core_sys_conf_settings`? | **Yes in production, fatal in dev.** Keys are filtered against a `SELECT conf_key … IN (…)`; an unknown key hits `continue` — except under `\IPS\IN_DEV`, where it throws `\InvalidArgumentException('unknown_setting: …')`. `Form::saveAsSettings()` delegates straight to `changeValues()`, so a form field whose key was never installed is silently discarded | 4.7.22 | read | `system/Settings/Settings.php:206-249`; `system/Helpers/Form/Form.php:750-764` | 2026-08-24 |
| U11 | Does uninstalling clean up settings and widgets? | **Yes, the core does it itself** — no application code required | 4.7.22 | read | `system/Application/Application.php:4649` (`DELETE FROM core_sys_conf_settings WHERE conf_app=?`), `:4938` (`DELETE FROM core_widgets WHERE app = ?`) | 2026-08-24 |
| U12 | How is a hook file transformed before it runs? | `"namespace {$namespace}; " . str_replace('_HOOK_CLASS_', $realClass, file_get_contents($file))`, then `@eval()`. The leading `//` is **not** stripped — it must comment out `<?php`, which `eval()` rejects. On success `$realClass` becomes `$data['class']`, so several hooks on one class chain by inheritance | 4.7.22 | read | `init.php:943-950` (`monkeyPatch()`) | 2026-08-24 |
| U12b | What are `$namespace` and `$realClass` for our two hooks? | Derived by the autoloader: the class name is split, the last segment is the class, the rest is the namespace, and `$realClass` starts as `_{$class}`. So `\IPS\Content\Comment` → namespace `IPS\Content`, parent `_Comment`; `\IPS\Member` → namespace `IPS`, parent `_Member`. The plan's blanket claim that `$namespace` is `IPS` holds only for the Member hook | 4.7.22 | read | `init.php:703-705` (split), `:912` (`monkeyPatch($namespace, $class, …)`), `:925` (`$realClass = "_{$finalClass}"`) | 2026-08-24 |
| U12c | What class name does the Suite give a hook from an application? | `"{$app}_hook_{$filename}"` — for this app, `spamtroll_hook_Comment` and `spamtroll_hook_Member`, matching the two files in `hooks/` | 4.7.22 | read | `system/Plugin/Hook.php:247` (inside `writeDataFile()`) | 2026-08-24 |

## Requires a test install

These are not answerable by reading the Suite. They need a scratch installation —
**never** a production forum (see `docs/SUITE-LAB.md`).

| # | Question | Why source is not enough | Experiment | Status |
|---|---|---|---|---|
| U7 | Does the stats widget render with a template imported at `location='admin'`? | Template resolution runs through the theme compiler and `core_theme_templates`; only runtime shows it | Place the widget in a forum sidebar and render | REQUIRES TEST INSTALL |
| U13 | IPS 5 — hook mechanics and the `_` class prefix | We hold no 5.x sources, and supporting 5.x is not a current goal | Product decision | REQUIRES TEST INSTALL |
| U14 | Is `\IPS\Data\Store` shared across php-fpm workers? | Depends on the store backend configured for a given install | Read the configured backend, then two concurrent requests | REQUIRES TEST INSTALL |

Nothing in this application depends on an unanswered row: the circuit breaker
fails open regardless of U14 (`sources/Scanner/Breaker.php`), and the widget is
untouched by this work.
