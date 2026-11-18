# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package follows
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- `BannerstopMentionApiClientBundle` with the configuration `bannerstop_mention_api_client`: `base_url`, `api_key` or
  `client_credentials`, `timeout`, `cache`, `http_client`, `request_factory`, `stream_factory` and `generator`. A wrong
  configuration is rejected with a message that names the option.
- An autowirable `Bannerstop\MentionApi\MentionApiClient`; by default it uses the Symfony HTTP client with the configured
  timeout and keeps the access token of client credentials in `cache.app`.
- The console command `mention-api:generate`, which fetches the OpenAPI document of your Consumer with the configured
  client and writes the typed wrappers of the core package; `--check` fails when the committed classes are out of date.
- Support for PHP 7.4 to 8.5 and Symfony 5.4, 6.4, 7.x and 8.x.
