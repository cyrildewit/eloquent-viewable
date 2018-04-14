# Contributing

Thank you for considering contributing to Eloquent Viewable!

We accept contributions via pull requests on [GitHub]. Please review these guidelines before submitting any pull requests.

## Guidelines

* Please follow the [PSR-2 Coding Style Guide](https://www.php-fig.org/psr/psr-2/), enforced by [StyleCI].
* One pull request per feature (send multiple if you want to do more than one thing).
* Add tests if you've added something new (ensure that the current tests pass).
* Send a coherent commit history (make sure each individual commit in your pull request is meaningful).
* Document any change in behaviour (make sure the `README.md` is kept up-to-date).
* Strictly follow our [Git Commit Guidelines](#git-commit-guidelines)!
* Please remember that we follow [SemVer](http://semver.org/).

### Git Commit Guidelines

Just like [Angular](https://github.com/angular/material/blob/master/.github/CONTRIBUTING.md#-git-commit-guidelines), we have very precise rules over how our git commit messages can be formatted. This section is almost fully adapted. &copy; Angular 2018.

#### Commit Message Format

```html
<type>(<scope>): <subject>
<BLANK LINE>
<body>
<BLANK LINE>
<footer>
```

> Any line of the commit message cannot be longer 100 characters!
> This allows the message to be easier to read on github as well as in various git tools.

##### Type

Must be one of the following:

* **feat:** a new feature
* **fix:** a bug fix
* **style:** changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc.)
* **refactor:** a code change that neither fixes a bug nor adds a feature
* **test:** adding missing tests
* **chore:** changes to the build process or auxiliary tools and libraries such as documentation generation

##### Scope

The scope could be anything specifying the place of the commit change.

##### Subject

The subject contains succinct description of the change:

* use the imperative, present tense: "change" not "changed" nor "changes"
* don't capitalize first letter
* no dot (.) at the end

##### Body

Just as in the **subject**, use the imperative, present tense: "change" not "changed" nor "changes" The body should include the motivation for the change and contrast this with previous behavior.

##### Footer

The footer should contain any information about **Breaking Changes** and is also the place to reference GitHub issues that this commit **Closes**.

> Breaking Changes are intended to highlight (in the ChangeLog) changes that will require community users to modify their code with this commit.

## Running Tests

Before you can run the tests, you have to install the package dependencies via [Composer](https://getcomposer.org/)!

```winbatch
composer install
```

Then run PHPUnit:

```winbatch
vendor/binphpunit
```

When you make a pull request, the tests will be automatically run again by [Travis CI](https://travis-ci.org/) on different PHP versions.

StyleCI is also active to automatically fix any code style issues.

[GitHub]: https://github.com/cyrildewit/laravel-page-view-counter/pulls
[StyleCI]: https://styleci.io/
