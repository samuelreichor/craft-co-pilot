# Release Notes for coPilot

## 1.2.0 - 2026-09-23

### Changed
- Add Claude Fable 5.1 (`claude-fable-5-1`) and Claude Opus 5.5 (`claude-opus-5-5`) to the Anthropic provider
- Add GPT-6 Astra, Sol and Luna (`gpt-6-astra`, `gpt-6-sol`, `gpt-6-luna`) to the OpenAI provider
- Add `gemini-3.8-flash`, `gemini-3.7-flash`, `gemini-3.6-flash` and `gemini-3.5-flash-lite` to the Gemini provider
- Remove the shut-down `gemini-3-flash-preview` from the Gemini provider
- Remove the legacy `gpt-4o`, `o3` and `o4-mini` from the OpenAI provider
- Change the default models to `claude-opus-5-5` (Anthropic), `gpt-6-sol` (OpenAI) and `gemini-3.8-flash` (Gemini)
- Use `gemini-3.5-flash-lite` for Gemini titles and API key checks, since Google now restricts Gemini 2.5 access to existing users

## 1.1.0 - 2026-08-10

### Features

- Gate write tools behind an in-code approval flow in supervised mode: the agent pauses, shows the exact changes, and only executes after the user approves
- Truncate oversized tool results before they enter the model context (`maxToolResultTokens`)
- Add support for Opus 5 to Anthropic provider.

### Fixed

- Unify the streaming and non-streaming agent loops (empty-stream retries now work on every iteration, stream errors are persisted)
- Add a wall-clock budget (`maxAgentSeconds`) and an optional token budget (`maxTokensPerRequest`) per request
- Debug exports now record the provider that was actually used for a turn
- Enforce the changeExecutionMode, changeProvider and changeModel permissions server-side instead of only hiding the UI controls
- Require the editOtherUsersChats permission to append to or compact another user's conversation
- Validate tool arguments against their JSON schema before execution
- The harness now owns the internal `_siteHandle` argument so the model can no longer redirect tool calls to a different site

## 1.0.5 - 2026-07-13

- Add support for Opus 4.8, Sonnet 5 and Fable 5 to Anthropic provider.
- Add support for GPT 5.6 Sol and GPT 5.5 to OpenAi provider.
- Add support for Gemini 3.5 Flash to Gemini provider.

## 1.0.4 - 2026-05-22

- Prevent errors in entries when no api key is configured

## 1.0.3 - 2026-04-21

- Add support for opus 4.7
- Add support for enabled field in LLMify settings

## 1.0.2 - 2026-04-02

- Only show default provider options that are actually configured
- Harden up provider init event
- Rename plugin name from coPilot to Copilot

## 1.0.1 - 2026-03-30
- Change gitHub url from craft-coPilot to craft-co-pilot

## 1.0.0 - 2026-03-29
- Initial release
