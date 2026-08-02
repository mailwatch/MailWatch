# Templates

Twig templates for the web interface. They live outside the document root and
are rendered by `MailWatch\Presentation\TemplateRenderer`, which is built by
`ApplicationFactory::templateRenderer()`.

## Conventions

- one directory per feature, plus `partials/` for fragments shared between
  features;
- file names end in `.html.twig`;
- a partial that is never rendered on its own starts with an underscore, for
  example `partials/_navigation.html.twig`.

## What templates may assume

Auto-escaping is on and `strict_variables` is enabled: rendering fails on an
undefined variable rather than printing nothing. Pass every variable a template
uses, explicitly.

Translations are available as a function:

```twig
<title>{{ __('mwforms03') }}</title>
```

Its output is treated as HTML and is not escaped, because the language files are
part of the installation and `__()` adds markup of its own when `DEBUG` is
enabled. Never pass request data through it.

## Compiled cache

Compiled templates are written to `var/cache/twig`, which is not part of the
repository. If that directory cannot be created or written, templates are
compiled on each request instead — slower, but working.
