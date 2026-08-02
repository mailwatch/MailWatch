# Templates

Twig templates for the web interface. They live outside the document root and
are rendered by `MailWatch\Presentation\TemplateRenderer`, which is built by
`ApplicationFactory::templateRenderer()`.

## What is here

`layout.html.twig` is the page frame for controllers: a template extending it
fills in the `content` block and nothing else.

The same frame is produced for the page scripts that have not been converted
yet, because `html_start()` and `html_end()` render the partials directly:

| Partial | Covers |
|---|---|
| `partials/_head.html.twig` | doctype to the opening `<body>` |
| `partials/_page_header.html.twig` | logo, message jump form, status panels, and the open content cell |
| `partials/_navigation.html.twig` | the navigation row and the language selector |
| `partials/_footer.html.twig` | closes what the page header opened, then the page footer |

`_page_header.html.twig` receives the status panels, the traffic graph and
today's statistics as markup already produced by the `print*` functions in
`functions.php`. Each becomes a template of its own when its feature is
converted; until then it is inserted unescaped.

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
