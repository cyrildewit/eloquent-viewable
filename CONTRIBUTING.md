# Contributing

Contributions are very welcome!

We accept contributions via pull requests on [GitHub]. Please review these guidelines before submitting any pull requests.

## Guidelines

* Please follow the PSR-2 Coding Style Guide (StyleCI is active).
* Create feature branches.
* One pull request per feature (send multiple if you want to do more than one thing).
* Add tests if you've added something new (ensure that the current tests pass).
* Send a coherent commit history (make sure each individual commit in your pull request is meaningful).
* Document any change in behaviour (make sure the `README.md` is kept up-to-date).
* Please remember that we follow [SemVer](http://semver.org/).

## Running Tests

Before you can run the tests, you have to install the package dependencies via [Composer](https://getcomposer.org/)!

```winbatch
composer install
```

Then run PHPUnit:

```winbatch
vendor/binphpunit
```

When you make a pull request, the tests will be automatically run again by [Travis CI](https://travis-ci.org/).

StyleCI is also active to automatically fix any code style issues.

[GitHub]: https://github.com/cyrildewit/laravel-page-view-counter/pulls
