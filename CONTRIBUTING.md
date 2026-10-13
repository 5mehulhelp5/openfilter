# Contributing

Thank you for helping. A few rules keep this module small and trustworthy.

## Scope

OpenFilter's scope per release is defined in the public roadmap. Features outside the current release are welcome as issues first, so the design can be discussed before code is written.

## Clean-room rule

All code must be written from scratch. Do not install, open, copy or adapt code, templates, CSS or assets from paid Magento extensions (layered navigation or any other), even "just to see how they do it". Only Magento Open Source, Mage-OS, Hyvä (OSL/AFL), OpenSearch documentation and other OSL/MIT/GPL code may be referenced. No vendor names in code, package names or the admin UI.

## Workflow

- Branch from `main`, use [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `test:`, `docs:`, `chore:`).
- Every change ships with tests (PHPUnit unit and/or Magento integration tests against a real OpenSearch).
- CI must be green: PHPCS (Magento2 standard), PHPStan level 6, unit and integration tests. Changes in `Model/Search`, `Model/Index` or `Model/Layer` run the full matrix (2.4.7 with Elasticsearch 8 and OpenSearch, 2.4.8, 2.4.9) before merge.
- Shared helpers come from `iranimij/module-base` (`TypedReader` for configuration, `SafeJson` for JSON); generic helpers belong there, not here.
- Public classes and methods carry PHPDoc; `Api/` interfaces follow strict semver.

## Running the tests locally

From a Magento root where the module is installed:

```bash
vendor/bin/phpcs --standard=Magento2 vendor/iranimij/openfilter
vendor/bin/phpstan analyse -c vendor/iranimij/openfilter/phpstan.neon
vendor/bin/phpunit -c vendor/iranimij/openfilter/phpunit.xml.dist
cd dev/tests/integration && ../../../vendor/bin/phpunit -c phpunit.xml ../../../vendor/iranimij/openfilter/Test/Integration
```

The development store with Hyvä, Varnish, enforced CSP and OpenLabel is [openfilter-dev-env](https://github.com/iranimij/openfilter-dev-env).
