# bannerstop/mention-api-client-bundle

Symfony integration of [bannerstop/mention-api-client](https://github.com/bannerstop/mention-api-client),
the PHP client for the `/v2` interface of mention-api. The bundle gives you:

- **Configuration** of the base URL, the credential (API key or OAuth 2.0 client credentials),
  the cache pool for the access token and the timeout
- **An autowirable `MentionApiClient`** to run Queries and Routines
- **The generator for typed wrappers as a console command**, `mention-api:generate`

## Versions

There is one major version for every supported Symfony and PHP version. A new major version is released only when
a minimum version rises or a range cannot be held any longer; everything else ships in a minor version.

| Version | PHP        | Symfony                |
|---------|------------|------------------------|
| 1.x     | 7.4 - 8.5+ | 5.4, 6.4, 7.x and 8.x |

The bundle requires `bannerstop/mention-api-client` `^1.0` and follows [Semantic Versioning](https://semver.org/).
Classes marked `@internal` are not part of the public API.

## Installation

```bash
composer require bannerstop/mention-api-client-bundle
```

The bundle talks to mention-api through a [PSR-18](https://www.php-fig.org/psr/psr-18/) client. By default it uses the
Symfony HTTP client, so install it together with PSR-17 factories:

```bash
composer require symfony/http-client nyholm/psr7
```

Register the bundle if Symfony Flex does not do it for you:

```php
// config/bundles.php
return [
    // ...
    Bannerstop\MentionApiClientBundle\BannerstopMentionApiClientBundle::class => ['all' => true],
];
```

## Configuration

Your app calls mention-api as a *Consumer* with its own credential. The Scope of the Consumer decides which Queries and
Routines it may call. Pick one of two ways to authenticate.

### API key

```yaml
# config/packages/bannerstop_mention_api_client.yaml
bannerstop_mention_api_client:
    base_url: 'https://mention-api.example.com' # without /v2
    api_key: '%env(MENTION_API_KEY)%'
```

### OAuth 2.0 client credentials

Any OAuth 2.0 server that issues tokens mention-api accepts will do; pass its token endpoint (the example below is the
shape Keycloak uses). The client must be allowed the client-credentials grant and the scope `mention-api`. The client
fetches the access token itself and keeps it in the cache until shortly before it expires.

```yaml
bannerstop_mention_api_client:
    base_url: 'https://mention-api.example.com'
    client_credentials:
        token_url: 'https://sso.example.com/realms/example/protocol/openid-connect/token'
        client_id: 'my-app'
        client_secret: '%env(MENTION_API_CLIENT_SECRET)%'
        # scope: 'mention-api'   # this is the default
    cache: 'cache.app'           # this is the default
```

Use either `api_key` or `client_credentials`, not both. Use an API key for a web request that has no shared cache:
without a cache that every process can reach, each PHP process fetches its own token.

### All options

```yaml
bannerstop_mention_api_client:
    base_url: 'https://mention-api.example.com'  # required, http(s), without /v2
    api_key: null                                # the API key of your Consumer
    client_credentials:                          # or: OAuth 2.0 client credentials
        token_url: ~                             # required in this block
        client_id: ~                             # required in this block
        client_secret: ~                         # required in this block
        scope: 'mention-api'
    timeout: 30.0                                # seconds, see "Timeout"
    cache: 'cache.app'                           # service id of a PSR-16 or PSR-6 cache; null = no cache
    http_client: null                            # service id of a PSR-18 client; null = the Symfony HTTP client
    request_factory: null                        # service id of a PSR-17 request factory
    stream_factory: null                         # service id of a PSR-17 stream factory
    generator:                                   # defaults of mention-api:generate
        namespace: null                          # e.g. 'App\Mention'
        directory: null                          # relative to the project directory, e.g. 'src/Mention'
```

Every value may be an environment variable, for example `timeout: '%env(float:MENTION_API_TIMEOUT)%'`. A wrong
configuration stops the container build with a message that names the option; values that come from environment
variables are checked when the client is created.

`cache` takes the service id of a PSR-16 cache or of a PSR-6 pool such as `cache.app` or a pool of your own. The access
token is stored under a key derived from the token URL, the client id and the scope, so pools can be shared. Whoever can
read the cache can read access tokens: use a pool that only your application can read.

### Timeout

`timeout` is the number of seconds to wait for an answer, 30 by default. With the default HTTP client it limits both the
time without data and the duration of the whole call, so raise it for Queries that take long. If you set `http_client`
to your own PSR-18 client, the timeout reaches it only when the client implements
`Bannerstop\MentionApi\Http\TimeoutAware`; **otherwise set the timeout on that client yourself.**

### Another HTTP client

Any PSR-18 client in the container will do. The bundle takes the PSR-17 factories from the container (the aliases
`Psr\Http\Message\RequestFactoryInterface` and `Psr\Http\Message\StreamFactoryInterface`), else `nyholm/psr7`, or
the factories you name:

```yaml
bannerstop_mention_api_client:
    base_url: 'https://mention-api.example.com'
    api_key: '%env(MENTION_API_KEY)%'
    http_client: 'App\Http\MentionHttpClient'   # a service that implements Psr\Http\Client\ClientInterface
    request_factory: 'app.psr17_factory'
    stream_factory: 'app.psr17_factory'
```

## Usage

Inject `Bannerstop\MentionApi\MentionApiClient` (the examples use constructor property promotion, PHP 8.0 or later; the bundle itself runs on PHP 7.4):

```php
use Bannerstop\MentionApi\MentionApiClient;

final class OrderReport
{
    public function __construct(private MentionApiClient $mention)
    {
    }

    public function total(int $orderId): string
    {
        $result = $this->mention->run('getOrderData', ['orderId' => $orderId]);

        foreach ($result as $row) {
            return $row['total'];
        }

        throw new \RuntimeException(sprintf('No order data (request %s).', $result->getRequestId()));
    }
}
```

Queries, Routines, errors (one exception class per error code, each with the request id) and retries (Queries only,
never Routines) are described in the
[README of the core package](https://github.com/bannerstop/mention-api-client#readme).

## Typed wrappers

The generator reads the OpenAPI document of your Consumer (`GET /v2/openapi.json`, exactly the Queries and Routines your
Consumer may call) and writes classes for your app: per Query a Parameters class, a Row class and a typed method, the
same for Routines. It fetches the document with the client of your application, so it uses your credentials and your
configuration, and it writes the files of the core generator, with the same output.

```bash
bin/console mention-api:generate --namespace='App\Mention' --dir=src/Mention
```

Commit the output. Nothing is generated at runtime, and the generated code calls the client, so authentication, errors,
retries and request ids behave as with `run()`. `--dir` is relative to the project directory. Set the defaults once and
leave both options out:

```yaml
bannerstop_mention_api_client:
    # ...
    generator:
        namespace: 'App\Mention'
        directory: 'src/Mention'
```

Register the generated classes as services and inject them like the client:

```yaml
# config/services.yaml
services:
    App\Mention\Queries: ~
    App\Mention\Routines: ~
```

```php
use App\Mention\Queries;
use App\Mention\Query\GetInvoiceTotalsParameters;

final class InvoiceReport
{
    public function __construct(private Queries $queries)
    {
    }

    public function totals(int $customerId): void
    {
        $rows = $this->queries->getInvoiceTotals(new GetInvoiceTotalsParameters($customerId, '2026-01-01', '2026-01-31'));

        foreach ($rows as $row) {           // GetInvoiceTotalsRow objects
            echo $row->invoiceId, ' ', $row->total, "\n";
        }
        echo $rows->getRequestId();
    }
}
```

The classes, the Parameters and the Rows are described in the
[README of the core package](https://github.com/bannerstop/mention-api-client#typed-wrappers).

### Options of the command

| Option | Meaning |
| --- | --- |
| `--namespace=<ns>` | PHP namespace of the generated classes (default: `generator.namespace`) |
| `--dir=<dir>` | Directory to write them to (default: `generator.directory`) |
| `--check` | Write nothing; exit with status 1 when the files differ from what would be generated |
| `--source=<file>` | Read the OpenAPI document from a file instead of fetching it |
| `--php=<7.4\|8.2>` | The PHP the code is written for; by default decided by `require.php` of your `composer.json` |
| `--composer=<file>` | The `composer.json` to read the PHP version from |

Exit status: 0 success, 1 the files differ (`--check`), 2 error.

### Keep the classes current

The output is deterministic. `--check` writes nothing, lists the files that differ and exits with status 1, so that your
CI notices when the catalog of your Consumer changed:

```bash
bin/console mention-api:generate --check
```

Run the command again without `--check` and commit the diff when it fails. The command only ever touches files that it
wrote itself.

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

The tests boot a small Symfony application with the bundle and answer its HTTP client with scripted responses; no network
is needed. CI runs them on PHP 7.4 to 8.5 with Symfony 5.4, 6.4, 7.4 and 8.1, and with the lowest allowed dependencies.

## Security

See [SECURITY.md](SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).
