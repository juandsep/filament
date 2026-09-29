# Contributing

Contributions are welcome and will be credited. Please read this before opening an issue or a pull request.

## Before you open an issue

- Make sure you can reproduce the problem, and that it isn't already reported.
- Leave out API keys and real customer data. If the problem depends on a row's text, a made-up example is enough.

## Before you open a pull request

- Check that nobody is already working on the same thing.
- Keep one feature or fix per pull request, with a history where every commit makes sense on its own.
- Add tests. They must not call a real API: use the fake driver (`Vibefilter\Filament\Drivers\FakeDriver`) or `Http::fake()`.
- Update `README.md` and `CHANGELOG.md` if the behaviour changes.
- Don't break the public API: the project follows [Semantic Versioning](https://semver.org/).

## Running the checks

```bash
composer test      # tests
composer lint      # code style (Laravel Pint)
composer analyse   # static analysis (PHPStan)
```

The same checks run on every pull request.
