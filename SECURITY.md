# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| 2.x | ✅ |
| 1.x | ❌ |

## Reporting a vulnerability

Please do not open a public issue for security problems.

Email **witch5062@gmail.com** with a description of the issue and, if possible, steps to
reproduce it. You will get an acknowledgement, and a fix or an explanation once the
report has been reviewed.

## Notes for users of this package

- API credentials are handled by [`laravel/ai`](https://github.com/laravel/ai) and read
  from your environment. This package never logs or stores them.
- Model attributes are sent to the configured AI provider in order to generate a value.
  Do not declare AI attributes on models holding secrets or personal data you are not
  permitted to share with a third party. To keep data on your own hardware, use the
  Ollama provider.
- Generated values are written to your cache store. Treat that cache with the same
  sensitivity as the source data.
