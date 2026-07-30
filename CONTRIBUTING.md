# Contributing

Thanks for taking the time to contribute. Issues and pull requests are welcome.

## Getting set up

```bash
git clone https://github.com/parselynk/laravel-ai-attributes.git
cd laravel-ai-attributes
composer install
```

Requires PHP 8.3 or newer.

## Running the test suite

```bash
composer test
```

The suite uses [Pest](https://pestphp.com) and [Orchestra Testbench](https://packages.tools/testbench).
Every AI call is faked, so no API keys and no network access are needed.

## Code style

The project uses [Laravel Pint](https://laravel.com/docs/pint). Run it before committing:

```bash
composer format
```

Continuous integration fails on unformatted code, so this is not optional.

## Trying changes against a real provider

Copy `.env.example` to `.env`, add a provider credential, then open a REPL with the
package loaded:

```bash
composer playground
```

`.env` is gitignored. Never commit real credentials.

If you have [Ollama](https://ollama.com) installed, you can develop without paying for
API calls — see the Ollama section of the README.

## Pull requests

- One logical change per pull request.
- Add or update tests for any behaviour you change.
- Make sure `composer test` and `composer format` both pass.
- Update `README.md` and `CHANGELOG.md` when behaviour or public API changes.

## Reporting bugs

Open an issue with the Laravel version, PHP version, the relevant `$aiAttributes`
definition, and what you expected versus what happened. A failing test is the most
useful bug report of all.
