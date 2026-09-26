# Architecture

Orin Core is split into three layers: **Support**, **AI** and **AI/Providers**.

```
src/
  AI/
    ProviderInterface.php      contract every provider implements
    ProviderResponse.php       immutable response value object
    Exceptions.php             AI-specific exception types
    ResilientAIEngine.php      orchestrates providers, retries, failover
    BanglishPrompt.php         Banglish detection + prompt normalisation
    PriceGuard.php             pre-flight token/cost estimation
    PriceGuardResult.php       result of a price guard decision
    Providers/
      AbstractProvider.php     shared HTTP + parsing helpers
      OpenAIProvider.php
      GeminiProvider.php
      OpenRouterProvider.php
  Support/
    Config.php                 env-backed configuration
    Logger.php                 simple file logger
    Container.php              tiny service container
    Http/
      HttpResponse.php
      HttpClientException.php
config/
  config.php                   array-based configuration
  schema.sql                   persistence schema
```

## Request lifecycle

1. **Entry** - `ResilientAIEngine::complete()` is called with a prompt.
2. **Banglish pass** - `BanglishPrompt` detects romanised Bengali and, if
   found, appends a system instruction to the prompt.
3. **Price guard** - `PriceGuard::inspect()` estimates input/output tokens and
   cost. If any limit is exceeded it returns a blocked `PriceGuardResult` and
   the request never leaves the process.
4. **Provider loop** - for each provider in priority order:
   - the provider's `complete()` is called,
   - on a transient error it is retried up to `max_retries`,
   - if it still fails, the engine moves to the next provider.
5. **Response** - the first successful `ProviderResponse` is returned.

## Failure model

- `ProviderException` - a provider responded with an error payload.
- `TransportException` - the HTTP call itself failed (timeout, DNS...).
- `AllProvidersFailedException` - every provider was exhausted.

Retries apply to both exception types. Only a successful HTTP 2xx response
with a parseable body stops the loop.

## Extension points

Implement `ProviderInterface` and register your class with the engine to add a
new backend. No other file needs to change.
