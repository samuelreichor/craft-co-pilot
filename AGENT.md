# AGENT.md

Binding code standards and architecture principles for the Craft CMS AI Agent Plugin. This document must be followed during development.

---

## Language

All generated code must always be written in English. This applies to everything: class names, method names, variable names, constants, enum values, comments, PHPDoc blocks, commit messages, error messages, log messages, database column names, event names, and inline documentation. No exceptions.

---

## Constants (`constants.php`)

Define all reusable values centrally – no magic strings in the code:

```php
final class Constants
{
    // Table Names
    public const TABLE_CONVERSATIONS = '{{%aiagent_conversations}}';
    public const TABLE_AUDIT_LOG = '{{%aiagent_audit_log}}';

    // Cache Keys
    public const CACHE_SCHEMA_PREFIX = 'aiagent.schema.';
    public const CACHE_BLOCKLIST = 'aiagent.blocklist';

    // Project Config Keys
    public const PC_BLOCKLIST = 'aiagent.blocklist';
    public const PC_BRAND_VOICE = 'aiagent.brandVoice';

    // Permission Keys
    public const PERMISSION_USE_AGENT = 'aiagent-useAgent';
    public const PERMISSION_MANAGE_SETTINGS = 'aiagent-manageSettings';
    public const PERMISSION_VIEW_AUDIT_LOG = 'aiagent-viewAuditLog';
}
```

---

## Enums

Use enums instead of strings wherever there are fixed options:

```php
enum Provider: string
{
    case OpenAI = 'openai';
    case Anthropic = 'anthropic';
}

enum ToolAction: string
{
    case Read = 'read';
    case Write = 'write';
    case Search = 'search';
}

enum SectionAccess: string
{
    case Blocked = 'blocked';
    case ReadOnly = 'readOnly';
    case ReadWrite = 'readWrite';
}

enum MessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}
```

---

## Events (Extensibility)

Fire custom events at every point where a developer should be able to modify or extend behavior:

```php
// Before an entry is sent to the AI → modify/exclude fields
Event::on(
    ContextService::class,
    ContextService::EVENT_BEFORE_SERIALIZE_ENTRY,
    function(SerializeEntryEvent $event) {
        // $event->entry, $event->fields, $event->cancel
    }
);

// Before a tool call is executed → validate or block
Event::on(
    AgentService::class,
    AgentService::EVENT_BEFORE_TOOL_CALL,
    function(ToolCallEvent $event) {
        // $event->toolName, $event->params, $event->cancel
    }
);

// After a tool call → modify result or log
Event::on(
    AgentService::class,
    AgentService::EVENT_AFTER_TOOL_CALL,
    function(ToolCallEvent $event) {
        // $event->toolName, $event->params, $event->result
    }
);

// Extend system prompt → add custom instructions
Event::on(
    SystemPromptBuilder::class,
    SystemPromptBuilder::EVENT_BUILD_PROMPT,
    function(BuildPromptEvent $event) {
        // $event->sections[] ← add custom prompt sections
    }
);

// Register custom tools
Event::on(
    AgentService::class,
    AgentService::EVENT_REGISTER_TOOLS,
    function(RegisterToolsEvent $event) {
        // $event->tools[] ← add custom tools
        $event->tools[] = new MyCustomTool();
    }
);

// Register custom providers
Event::on(
    ProviderService::class,
    ProviderService::EVENT_REGISTER_PROVIDERS,
    function(RegisterProvidersEvent $event) {
        $event->providers[] = new MistralProvider();
    }
);
```

---

## Controller Principle

Controllers are thin – request handling, validation, and service calls only:

```php
// ✅ Correct
class ChatController extends Controller
{
    public function actionSend(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $message = $this->request->getRequiredBodyParam('message');
        $contextId = $this->request->getBodyParam('contextId');

        $result = Plugin::getInstance()
            ->agentService
            ->handleMessage($message, $contextId);

        return $this->asJson($result);
    }
}

// ❌ Wrong – no business logic in the controller
class ChatController extends Controller
{
    public function actionSend(): Response
    {
        // Do not: load entry, serialize, build prompt,
        // call provider, execute tools, etc.
    }
}
```

---

## Simplicity

- If a class exceeds ~200 lines → split it
- If a method does more than one thing → split it
- No abstraction without at least two concrete use cases
- Prefer explicit over clever
- Comments explain *why*, not *what*

---

## Security

No security vulnerabilities. Every piece of code must be checked against these points:

- **SQL Injection:** Never concatenate raw SQL with user input. Always use Craft's Query Builder or prepared statements with parameter binding.
- **XSS:** All output in the CP must be escaped. Twig: `{{ variable|e }}` or `{{ variable }}` (auto-escaped). PHP: `Html::encode()`. No `|raw` without explicit justification.
- **CSRF:** All POST requests must use `$this->requirePostRequest()`. Craft's CSRF token is automatically included in CP forms.
- **Mass Assignment:** No uncontrolled adoption of request data into models. Always explicitly define which fields may be set.
- **API Keys:** Read only from environment variables, never from DB, never from Project Config, never in frontend code.
- **Permissions:** Every tool call validates server-side against Craft Permissions + Blocklist. Never rely on frontend validation.
- **Deserialization:** No `unserialize()` on user input. Use JSON instead of PHP serialization.
- **File Access:** No path manipulation through user input. No `file_get_contents()` with uncontrolled paths.
- **Dependencies:** Only packages with known security. Run `composer audit` regularly.

---

## Licenses

- All dependencies must have an MIT-compatible license (MIT, BSD, Apache 2.0).
- No GPL-licensed packages – the plugin is commercially distributed.
- Check the license before adding new dependencies.
- `composer licenses` shows all licenses of current dependencies.
- Generated code must never originate from licensed or commercial plugins (e.g. SEOmatic, Craft Commerce, Formie). Reading/writing plugin field data is allowed, but reproducing their source code is not.

---

## Extensibility

The code must be built so that future features (Phase 2/3) don't trigger major refactoring:

- Tools implement a `ToolInterface` → add new tools without changing existing ones
- Providers implement a `ProviderInterface` → add new providers without changing existing ones
- Events at all extension points → third-party plugins can modify behavior
- Enums for fixed options → adding new options is a one-liner
- Constants for reusable values → rename in one place instead of searching everywhere
