# Contributing

Thank you for your interest in contributing to PHP Simple Framework.

## Before You Start

- For bugs and feature requests, open an issue first to discuss before writing code
- For security vulnerabilities, see [SECURITY.md](SECURITY.md) — do not open a public issue

## Developer Certificate of Origin

By contributing to this project you agree to the [Developer Certificate of Origin (DCO) v1.1](https://developercertificate.org/). This means you certify that you have the right to submit your contribution under the project's ISC license.

Sign off every commit with:

```bash
git commit -s -m "your commit message"
```

This adds a `Signed-off-by: Your Name <your@email.com>` line to your commit. Pull requests with unsigned commits will not be merged.

## How to Contribute

1. Fork the repository
2. Create a branch from `master`: `git checkout -b my-fix`
3. Make your changes
4. Sign off your commits (see above)
5. Open a pull request against `master`

## Code Style

- PHP **8.3+** with `declare(strict_types=1)` in every file
- Follow the existing code conventions — spacing, naming, and structure
- No `@author` tags or license headers in individual files
- No unnecessary docblocks — only add one if it says something the code doesn't

## Testing

The framework uses PHPUnit. Run the test suite before submitting:

```bash
bin/phpunit
```

All existing tests must pass. Bug fixes should include a test that reproduces the issue.
