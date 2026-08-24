<?php

declare(strict_types=1);

/**
 * PHPStan stubs for the IPS Community Suite framework.
 *
 * IPS is closed-source and uses a custom autoloader that resolves
 * `_ClassName` source-file declarations to fully-qualified class
 * names at runtime. PHPStan can't see the live framework, so we
 * declare just enough surface here for the analyser to type-check
 * the application code.
 *
 * Hooks (`hooks/*.php`) are excluded from PHPStan analysis because
 * their `//<?php` first line is a HTML comment, not a PHP tag.
 */

namespace IPS {
    if (! defined('IPS\\SUITE_UNIQUE_KEY')) {
        define('IPS\\SUITE_UNIQUE_KEY', 'stub');
    }
    if (! defined('IPS\\ROOT_PATH')) {
        define('IPS\\ROOT_PATH', '/var/www/forum');
    }

    abstract class Application
    {
        public string $directory = '';
        public string $title = '';

        public function installOther(): void {}

        /**
         * @param array<string, mixed> $data
         */
        public static function constructFromData(array $data): static
        {
            return new static();
        }

        public static function load(string $key): static
        {
            return new static();
        }
    }

    class Settings
    {
        public bool $spamtroll_enabled = false;
        public string $spamtroll_api_key = '';
        public string $spamtroll_api_url = '';
        /** @var float|string */
        public $spamtroll_spam_threshold = 0.7;
        /** @var float|string */
        public $spamtroll_suspicious_threshold = 0.4;
        public bool $spamtroll_check_posts = true;
        public bool $spamtroll_check_messages = false;
        public bool $spamtroll_check_registrations = true;
        public string $spamtroll_action_blocked = 'block';
        public string $spamtroll_action_suspicious = 'moderate';
        public string $spamtroll_bypass_groups = '';
        /** @var int|string */
        public $spamtroll_bypass_min_posts = 0;
        /** @var int|string */
        public $spamtroll_log_retention_days = 30;
        /** @var int|string */
        public $spamtroll_timeout = 5;
        public string $spamtroll_sensitivity = 'balanced';
        public string $spamtroll_scan_scope = 'all';
        public string $spamtroll_quota_skipped_log = '';
        public bool $spamtroll_override_thresholds = false;
        public bool $spamtroll_anonymize_ip = false;
        /* Core settings the application reads but does not own. */
        public bool $spam_service_enabled = true;
        public string $reg_auth_type = 'none';

        protected static ?self $instance = null;

        public static function i(): self
        {
            if (static::$instance === null) {
                static::$instance = new static();
            }
            return static::$instance;
        }

        /**
         * Replace the singleton. Test-only seam: the live framework has no
         * such method, so nothing shipped may call it.
         */
        public static function setInstance(?self $instance): void
        {
            static::$instance = $instance;
        }

        /**
         * @param array<string, mixed> $newValues
         */
        public function changeValues(array $newValues): void
        {
            foreach ($newValues as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }

    class _Member
    {
        public int $member_id = 0;
        public int $member_posts = 0;
        public string $name = '';
        public string $email = '';
        public int $mod_posts = 0;
        public int $temp_ban = 0;
        public int $language = 1;
        public int $acp_language = 1;
        /** @var array<int, int> */
        public array $groups = [];

        public function isAdmin(): bool
        {
            return false;
        }

        public static function loggedIn(?Member $member = null): Member
        {
            return new Member();
        }

        public static function load(int $id): Member
        {
            return new Member();
        }

        public function language(): Lang
        {
            return new Lang();
        }

        /**
         * Untyped, like the Suite's own (`system/Member/Member.php:450`).
         *
         * @return mixed
         */
        public function save()
        {
            return null;
        }

        /**
         * Untyped on purpose: this is the Suite's own signature, character
         * for character (docs/SUITE-FACTS.md, U4a).
         *
         * @param mixed $type
         * @param mixed $emailAddress
         * @param mixed $spamCode
         * @param mixed $disposable
         * @param mixed $geoBlock
         *
         * @return int|null
         */
        public function spamService($type = 'register', $emailAddress = null, &$spamCode = null, &$disposable = false, &$geoBlock = false)
        {
            return null;
        }
    }

    class Member extends _Member {}

    class Lang
    {
        public int $id = 1;
        public string $short = 'en_US';
        public string $title = 'English';
        public bool $enabled = true;

        public static function defaultLanguage(): int
        {
            return 1;
        }

        public static function load(int $id): self
        {
            return new self();
        }

        public function get(string $key): mixed
        {
            return $key;
        }

        public function addToStack(string $key): string
        {
            return $key;
        }
    }

    class Log
    {
        /**
         * Test-only capture buffer. The live framework writes to core_log;
         * the suite reads this instead of reaching for a database.
         *
         * @var array<int, array{what: \Throwable|string, category: string}>
         */
        public static array $entries = [];

        public static function log(\Throwable|string $what, string $category = ''): void
        {
            static::$entries[] = ['what' => $what, 'category' => $category];
        }
    }

    class Db
    {
        public static function i(): self
        {
            return new self();
        }

        /**
         * @param array<int|string, mixed>|string|null $where
         * @return Db\Select<array<string, mixed>>
         */
        public function select(string $columns, string $table, $where = null, ?string $order = null, ?int $limit = null): Db\Select
        {
            return new Db\Select();
        }

        /**
         * @param array<string, mixed> $data
         */
        public function insert(string $table, array $data): int
        {
            return 0;
        }

        /**
         * @param array<string, mixed>                 $data
         * @param array<int|string, mixed>|string|null $where
         */
        public function update(string $table, array $data, $where = null): bool
        {
            return true;
        }

        /**
         * @param array<int|string, mixed>|string|null $where
         */
        public function delete(string $table, $where = null): bool
        {
            return true;
        }

        public function checkForTable(string $name): bool
        {
            return true;
        }

        /**
         * @param array<string, mixed> $columns
         */
        public function createTable(array $columns): bool
        {
            return true;
        }

        /**
         * @param array<int, mixed> $values
         */
        public function in(string $column, array $values, bool $not = false): string
        {
            return '';
        }
    }

    class Request
    {
        /** @var string|null */
        public $api_key;
        /** @var string|null */
        public $api_url;

        public static function i(): self
        {
            return new self();
        }

        public function ipAddress(): string
        {
            return '';
        }
    }

    class Output
    {
        public string $title = '';
        public string $output = '';
        /** @var array<string, array<string, mixed>> */
        public array $sidebar = ['actions' => []];
        /** @var array<int, string> */
        public array $cssFiles = [];
        /** @var array<int, string> */
        public array $jsFiles = [];

        public static function i(): self
        {
            return new self();
        }

        public function error(string $message, string $code, int $statusCode = 500, string $details = ''): never
        {
            throw new \RuntimeException($message);
        }

        public function redirect(\IPS\Http\Url $url, string $message = ''): never
        {
            throw new \RuntimeException('redirect');
        }

        /**
         * @param array<string, mixed> $data
         */
        public function json(array $data): void {}

        /**
         * @return array<int, string>
         */
        public function js(string $name, ?string $app = null, ?string $location = null): array
        {
            return [];
        }
    }

    class Theme
    {
        public static function i(): self
        {
            return new self();
        }

        public function getTemplate(string $group, string $app, string $location): mixed
        {
            return new class () {
                public function __call(string $name, mixed $args): string
                {
                    return '';
                }
            };
        }

        /**
         * @return array<int, string>
         */
        public function css(string $name, ?string $app = null, ?string $location = null): array
        {
            return [];
        }

        public static function master(): mixed
        {
            return new \stdClass();
        }

        public static function load(int $id): mixed
        {
            return new \stdClass();
        }

        public static function designersModeEnabled(): bool
        {
            return false;
        }
    }

    class Session
    {
        public static function i(): self
        {
            return new self();
        }

        public function csrfCheck(): void {}
    }

    class Dispatcher
    {
        public string $controllerLocation = 'admin';

        public static function i(): self
        {
            return new self();
        }

        public static function hasInstance(): bool
        {
            return true;
        }

        public function checkAcpPermission(string $key): void {}
    }

    class Task
    {
        public static function load(string $key): self
        {
            return new self();
        }
    }

    abstract class Widget
    {
        public string $key = '';
        public string $app = '';
        /** @var array<string, mixed> */
        public array $configuration = [];

        /**
         * @param array<string, mixed> $configuration
         */
        public function __construct(string $uniqueId, array $configuration = [], ?string $access = null, ?string $orientation = null) {}
    }

    class DateTime extends \DateTime {}

    class IPS
    {
        /** @var array<string, array<int, array{type: string, class: string}>> */
        public static array $hooks = [];
    }
}

namespace IPS\Member {
    class Group
    {
        /**
         * @return array<int, string>
         */
        public static function groups(bool $includeRoot = true, bool $includeGuest = false): array
        {
            return [];
        }
    }
}

namespace IPS\Db {
    /**
     * @template TRow
     * @implements \Iterator<int, TRow>
     */
    class Select implements \Iterator, \Countable
    {
        /**
         * @return TRow|mixed
         */
        public function first()
        {
            return [];
        }

        public function count(): int
        {
            return 0;
        }

        /** @return TRow */
        public function current(): mixed
        {
            return [];
        }

        public function next(): void {}

        public function key(): int
        {
            return 0;
        }

        public function valid(): bool
        {
            return false;
        }

        public function rewind(): void {}
    }
}

namespace IPS\Http {
    class Url
    {
        public static function external(string $url): self
        {
            return new self();
        }

        public static function internal(string $url, ?string $base = null): self
        {
            return new self();
        }

        /**
         * @param bool|int $followRedirects
         */
        public function request(?int $timeout = null, ?string $httpVersion = null, $followRedirects = 5): Request
        {
            return new Request();
        }

        public function csrf(): self
        {
            return $this;
        }

        public function __toString(): string
        {
            return '';
        }
    }

    class Request
    {
        /**
         * @param array<string, string> $headers
         */
        public function setHeaders(array $headers): self
        {
            return $this;
        }

        public function post(string $body): Response
        {
            return new Response();
        }

        public function get(): Response
        {
            return new Response();
        }
    }

    class Response
    {
        public int $httpResponseCode = 200;
        /** @var array<string, string>|null */
        public $httpHeaders = null;

        public function __toString(): string
        {
            return '';
        }
    }
}

namespace IPS\Content {
    abstract class _Comment
    {
        public int $id = 0;

        /**
         * @param mixed $item
         * @param mixed $comment
         * @param mixed $first
         * @param mixed $guestName
         * @param mixed $incrementPostCount
         * @param mixed $member
         * @param mixed $ipAddress
         * @param mixed $hiddenStatus
         * @param mixed $anonymous
         *
         * @return static
         */
        public static function create($item, $comment, $first = false, $guestName = null, $incrementPostCount = null, $member = null, ?\IPS\DateTime $time = null, $ipAddress = null, $hiddenStatus = null, $anonymous = null)
        {
            /* @phpstan-ignore-next-line new.static */
            return new static();
        }

        /**
         * @param \IPS\Member|null|false $member
         */
        public function hide($member, ?string $reason = null): void {}

        public function item(): Item
        {
            return new Item();
        }
    }

    abstract class Comment extends _Comment {}

    class Item
    {
        public int $id = 0;

        /**
         * @param \IPS\Member|null|false $member
         */
        public function hide($member, ?string $reason = null): void {}
    }
}

namespace IPS\core\Messenger {
    class Conversation extends \IPS\Content\Item {}
}

namespace IPS\Http\Request {
    class Exception extends \RuntimeException {}
}

namespace IPS\Dispatcher {
    abstract class Controller
    {
        public static bool $csrfProtected = false;

        public function execute(): void {}
    }
}

namespace IPS\Helpers {
    class Form
    {
        public function __construct(string $name = 'form', string $submit = 'save') {}

        public function addHeader(string $key): self
        {
            return $this;
        }

        public function addMessage(string $message): self
        {
            return $this;
        }

        public function add(\IPS\Helpers\Form\AbstractFormHelper $field): self
        {
            return $this;
        }

        /**
         * @return array<string, mixed>|false
         */
        public function values(bool $stripBypassGroups = false)
        {
            return false;
        }

        /**
         * @param array<string, mixed> $values
         */
        public function saveAsSettings(array $values): void {}

        public function __toString(): string
        {
            return '';
        }
    }
}

namespace IPS\Helpers\Form {
    abstract class AbstractFormHelper
    {
        /** @var mixed */
        public $defaultValue;

        public function __construct(
            string $name,
            mixed $defaultValue = null,
            bool $required = false,
            mixed $options = [],
            ?callable $customValidationCode = null,
            ?string $prefix = null,
            ?string $suffix = null,
            ?string $id = null,
        ) {}
    }

    class Text extends AbstractFormHelper {}
    class YesNo extends AbstractFormHelper {}
    class Select extends AbstractFormHelper {}
    class Number extends AbstractFormHelper {}
}

namespace IPS\Helpers\Table {
    class Db
    {
        public string $langPrefix = '';
        /** @var array<string, string> */
        public array $filters = [];
        /** @var array<string, string> */
        public array $include = [];
        public string $sortBy = '';
        public string $sortDirection = 'desc';
        /** @var array<string, callable> */
        public array $parsers = [];
        /** @var array<int, mixed> */
        public array $rowButtons = [];

        public function __construct(string $table, \IPS\Http\Url $url, mixed $where = null, mixed $extraWhere = null) {}

        public function __toString(): string
        {
            return '';
        }
    }
}

namespace IPS\Data {
    class Store
    {
        public static function i(): self
        {
            return new self();
        }

        public function __get(string $key): mixed
        {
            return null;
        }

        public function __set(string $key, mixed $value): void {}

        public function __isset(string $key): bool
        {
            return false;
        }

        public function __unset(string $key): void {}
    }

    class Cache
    {
        public static function i(): self
        {
            return new self();
        }

        public function clearAll(): void {}
    }
}
