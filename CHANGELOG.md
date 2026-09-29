# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-09-29

### Added

- A list item may be the value of the one slot its item shape renders, so a
  list of strings renders as a list of strings instead of a list of one-key
  maps: `Slot::each('xs', li(Slot::value('value')))` accepts `['a', 'b']` next to
  `[['value' => 'a'], ['value' => 'b']]`, and an item shape that reads several
  slots, reads its one slot as a nested `Slot::child()` / `Slot::each()`, or
  reads no slot at all keeps taking a real array scope. The binding is compiled
  from the item shape, so no API changed and existing array items render
  byte-identically.
- A list item that is not an array reports what the item shape expects:
  `slot 'items[]' must be an array, string given. The item shape of this slot
  reads 'a' and 'b', so each item must be an array.`
- A `null` list item is now bound to the slot it renders and fails as that slot
  does — `MissingSlotException: slot 'items[].value' is required but was null.`,
  the same outcome `[['value' => null]]` already had — instead of being
  rejected as a scope that is not an array.
- `pure help` is accepted as a name for the help, and a bare `pure` prints it on
  stdout with exit 0 rather than treating the missing subcommand as an error.
- Both commands accept `--` before their paths, so a directory whose name starts
  with a dash is reachable.
- `pure` reports a missing Composer autoloader as `run \`composer install\``
  instead of a raw `Error: Failed opening required`, and a top-level handler
  turns a crash anywhere in a command into one `pure: <reason>` line instead of
  a stack trace through the internal classes.
- `pure check` counts the `info` notes it printed, so the `checked N unit(s)`
  summary accounts for every line the run produced.
- The usage text of both commands documents the exit codes.

### Changed

- **Breaking** — a usage error exits `2` instead of `1`, so a CI script can tell
  "the command line was wrong" from "the code has errors". `0` and `1` keep
  their meaning; a script that tested `-eq 1` for a typo now has to accept `2`.
- Findings are printed as `path:line: level: message`. A finding about a fluent
  call carries the line of its `->prop(...)` setter, so an editor can jump to it;
  a unit-level finding names its file without a line.
- Errors and warnings go to stderr, and the `missing:` / `stale:` reports of
  `pure compile --check` with them. The `ok:` lines, the `info` notes and the
  summary are results and stay on stdout, so `pure check . 2>/dev/null` leaves
  only what passed.
- The `checked N unit(s)` summary appends `, N note(s)` when the run printed
  `info` findings.

### Fixed

- A misspelled required prop is reported as the unknown prop it is, with a
  `did you mean` pointing at the intended one. The unknown-prop check now runs
  before the missing-prop check, because a typo both introduces an unknown prop
  *and* leaves the real one missing — the old order reported the gap and never
  mentioned the typo. Each unknown prop now gets its own suggestion rather than
  only the first.

### Documentation

- The `.cmp.php` suffix is stated as the convention `pure compile` and
  `pure check` discover by, in the Quick Start and as a Troubleshooting entry
  covering both messages a wrong suffix produces (English and Chinese).
- The artifacts guide documents the `path:line: level: message` format, the
  stdout/stderr split and the three exit codes (English and Chinese).
- The examples guide's repository workflow runs the in-repo binary as
  `php bin/pure` — a clone has no `vendor/bin/pure`, because Composer creates
  that proxy for installed packages only — and the troubleshooting entry
  distinguishes the clone from an application that installed the package
  (English and Chinese).
- The guides read as usage documentation rather than project notes: the
  performance section states the cost model (what is paid once, per request, or
  per view) instead of the benchmark machine, date and per-case numbers; the
  upgrade page lists requirements instead of compatibility claims; and the
  documentation-site, license and CI remarks are gone (English and Chinese).
- The upgrading pages are trimmed to the requirements, the upgrade and rebuild
  checklist and the rollback steps; this changelog is the single place that
  states what changed and what a reader has to do about it (English and
  Chinese).
- `composer.json` states the project's `homepage` and its `support.source` /
  `support.issues` URLs, so Packagist shows them and the package's repository
  can be derived from its own metadata instead of the submitter's form.
- The Chinese upgrading page no longer claims the changelog describes an
  untagged release; `1.0.0` is tagged.
- The Chinese site's own labels (language menu, outline, footer, previous and
  next) are translated instead of falling back to the shared English chrome.
- The event-counter example attaches both buttons with `addEventListener`, which
  is what the events guide recommends, instead of using an inline `onclick` on
  one of them and a listener on the other.
- A unit's call function declares `mixed ...$children` only when its template
  reads the `children` slot; every other unit takes none and is written as
  `function Card(): Call { return component(__FUNCTION__); }`. The declaration
  follows the template because the two mistakes fail differently: a call
  function that forwards children to a template without the slot throws
  `component 'X' does not read children` on render, while a call function that
  takes none drops them silently, because PHP accepts extra arguments to a user
  function (`pure check` does not report it). The example units, the guides
  (English and Chinese), and the `register()` / `component()` docblocks follow
  the rule, and the component API page now demonstrates the `...$children` form
  beside the childless one.
- The English and Chinese guides are aligned where they had drifted: the
  Chinese pages gain the missing `echo Card()->...` call, the plain-view `@var`
  fence and the plain-view caveats that only the English pages carried, and the
  Chinese counterparts no longer merge paragraphs the English pages keep apart.
- `## [1.0.0]` had a link reference without a definition; the release now links
  to its tag page, like the `[Unreleased]` link above it.
- The documentation's PHP fences now say what they are: each example unit is
  shown as its own file (one unit file registers one component), and only
  self-contained snippets carry a `<?php` opener, so a fence that survives
  `php -l` is also a fence that runs.

### Examples

- The pricing example no longer wraps every feature in a one-key map: the DAO
  holds the feature strings it has, `Card()` binds them straight through, and
  the deck passes the record's features on instead of mapping them into
  `['value' => $feature]` and back. The rendered page is byte-identical.
- `examples/bootstrap/views/cover.php` called `renderHTML()` without importing
  it, so the bootstrap example's `/cover` route failed with `Call to undefined
  function renderHTML()` — the one route the upgrade guide tells you to check.
- `examples/event-counter/views/counter.cmp.php` and
  `examples/xml/views/xml.cmp.php` pointed at `*.shape.php` files that do not
  exist, and their call functions were the only example units without a
  docblock.

## [1.0.0] - 2026-09-26

First public release.

### Added

- `register()` takes the call function itself as its only form:
  `register(Icon(...), factory)` derives the component name and the unit file
  from that function by reflection (O(1), ~0.2µs), and inside the call
  function `component(__FUNCTION__, ...)` carries no literal either — the
  component name then exists exactly once per unit file and cannot drift
  between `register()` and `component()`. The factory may follow the call
  function positionally. The PHPStan rule resolves `register(Icon(...))` and
  `component(__FUNCTION__)`, so the unit is checked statically without any
  name literal.
- `Pure\StaticAnalysis\UnknownComponentRule`: a PHPStan rule (plus its
  collectors, enabled in phpstan.neon) that reports a literal
  `component('X')` / `Registry::component('X')` whose name no analysed
  `register(Icon(...))` or `Registry::register()` declares —
  the typo that otherwise only fails at render time, with a
  did-you-mean hint.
- `pure --version` (also `pure version` / `pure -v`) prints the installed
  package version.
- Component units: a `*.cmp.php` file registers a lazy template factory with
  `Pure\Component\register()` and defines the call function next to it.
  `component()` accepts the registered name next to a file path; a name
  and the path of its unit file resolve to the same renderer, the factory is only
  called when no fresh artifact serves the unit (and then once per compile
  generation), and duplicate registrations throw unless `override: true` is
  passed. The examples ship as units now.
- `pure compile` discovers `*.cmp.php` units next to `*.shape.php` templates
  and gained `--list` (`name -> file (component)`; a `#[Template]` builder is
  listed as `(template)`), so one command compiles a unit file through its
  registered factory.
- `ArtifactCompiler::buildUnit()` / `writeUnit()` compile a unit file
  (`*.shape.php` or `*.cmp.php`) whose shape is already known, so a `*.cmp.php`
  unit gets the same `*.pure.php` artifact and `*.plain.php` view as a shape
  file; `writeUnit()` skips files whose content is already current.
- Every artifact carries a one-line cache-version guard, so loading a
  `*.pure.php` written by another `Compile::CACHE_VERSION` throws a
  `RuntimeException` that names `pure compile` instead of failing on the
  `Renderer` constructor signature it describes. The check compares two integer
  literals, so it stays far cheaper than verifying the file content (which
  measured ~7 µs per artifact against ~0.5 µs to load it with opcache).
- `pure compile` rejects two source files that would write the same artifact
  (`a.shape.php` and `a.cmp.php` beside each other): both are reported, neither
  is written, and the command fails, so discovery order cannot decide which
  template owns `a.pure.php`.

- `Pure\Component\component()` renders a unit or a `*.shape.php` template in one
  expression (`component($file)->title($title)`), caching the binder per name or
  path; the rendered markup is the tree as written, and the document header of a
  full document is the caller's to prepend.
- Compiled rendering: `Pure\Compile\Compile::shape()` compiles a data-free shape
  tree with `Pure\Core\Slot` placeholders into a flat PHP renderer
  (`Shape`, `Renderer`). Static markup is escaped once at compile time and
  subtrees without slots are folded into literals.
- Slot types `Slot::value()`, `Slot::raw()`, `Slot::child()`,
  `Slot::each()` and `Slot::if()`, with `required(false)`
  and `default()` modifiers.
- `Pure\Core\MissingSlotException` with full slot paths for missing data, and
  `InvalidArgumentException` for non-stringable values and list contract
  violations.
- Structure fingerprints (`Shape::id()` / `Renderer::$id`) computed without
  compiling, used as cache keys.
- Optional on-disk renderer cache: `Compile::cachePath()`, `Compile::clearCache()`
  and `Compile::flush()`; cache files are content-addressed, written atomically
  and opcache-friendly.
- Development guard for shapes rebuilt per request: `Compile::guard()` /
  `PURE_COMPILE_GUARD=1` emits an `E_USER_WARNING` after repeated calls from
  the same call site.
- `bench/compare.php`, `bench/cache.php` and `bench/README.md` with recorded
  numbers, plus `composer bench`.
- `Shape::save($path, $data, ?string $header = null)`: renders a compiled shape
  to a file, prepending the document header of the root tag (`<!DOCTYPE html>`,
  the XML declaration) unless a header is given. Subclasses keep customizing that
  header by overriding the protected `Tag::defaultHeader()`; the compiled path
  reads it through an `@internal` `Tag::documentHeader()` accessor.
- `Pure\HTML\htmlVar()` for the `<var>` element: `var` is a PHP keyword and
  cannot name a function, so the helper carries the namespace prefix, as
  SVG already does for `<use>` (`svgUse()`) and `<switch>` (`svgSwitch()`);
  the magic static surface (`HTML::var()`) covers the element too.
- `pure compile --plain` also writes a dependency-free `*.plain.php` view:
  markup and native PHP (`htmlspecialchars()` with the renderer's flags,
  ordinary arrays and loops) that renders without purephp installed, loaded by
  extracting the data into locals. A plain view matches its artifact byte for
  byte on ordinary data, and gives the strict slot semantics to the artifact
  (a missing slot is an undefined variable, a `null` attribute prints empty);
  `--check --plain` reports stale or missing views.
- `pure compile <path>...` precompiles every `*.shape.php` file that returns a
  tag tree or a `Shape` into a sibling `*.pure.php` artifact (`bin/pure`). An
  artifact returns a `Renderer` without building the shape tree, so `require` is
  all production needs; the document header is the caller's to prepend.
  `--check` reports stale or missing artifacts for CI. Artifacts are written as
  readable templates (`<?= ... ?>` values, `if (...): ... endif;`,
  `foreach (...): ... endforeach;`) with the compiled closure defined once and
  slots read through `TemplateRuntime` accessors, which keep the required-slot,
  `default:` and escaping semantics in one place while rendering byte-identically
  to the flat source.
- Compiled guide and API documentation (English and Chinese).
- Plain views declare their root slots with `@var` annotations derived from the
  shape tree, so static analyzers read the extracted locals without an
  exclusion and without configuration: value slots are
  `scalar|null|\Stringable`, condition slots `mixed`, child and list scopes
  become array shapes and iterables of them, and odd slot names are declared on
  the loader's `$data` shape. The annotations add no output bytes.
- Development guard coverage beyond the shape-rebuild warning: with
  `Compile::guard(true)` / `PURE_COMPILE_GUARD=1`, a rendered template reports
  data keys it never reads (suggesting the closest slot, so a misspelled binding
  is visible instead of rendering as if the value were absent), and a `Tag`
  setter whose name is one edit away from a standard HTML/SVG attribute
  (`->clas(...)`, `->hreff(...)`) warns instead of silently creating a custom
  attribute. Each warning fires once per subject per process, and every check
  costs one property read when the guard is off.
- `Renderer::$slots`: the root slot manifest of a compiled renderer, embedded in
  artifacts too, so the unknown-key report needs neither the shape tree nor a
  recompile. `RootSlots` collects it while the shape compiles.
- `Tag::isDocumentRoot()`: whether a root tag heads a complete document (an
  `<html>` root, any XML root) and therefore owns a document header. An SVG tree
  is a fragment whose standalone declaration stays available through
  `documentHeader()` / `save()`.
- Missing-slot errors are actionable: `MissingSlotException` suggests the closest
  provided key (`slot 'title' is required but was not provided; did you mean
  'titel'?`) or lists the keys the scope did provide, and rendering through a
  call prefixes the component name or template path
  (`component 'Card': slot 'title' is required ...`).
- `pure check <path>...` validates the component contract statically: the slots
  a template reads against the keys its `prepare()` hook returns (a returned key
  the template does not read is an error with a `did you mean` suggestion, a
  required slot the hook does not return is an error), the hook's parameter types
  against the slot kinds (a list slot
  needs an iterable, a child scope an array, a text slot a stringable, a raw
  slot either), a unit without `prepare()` against the props its call sites bind,
  and a slot name one template uses as both a scalar and a scope. `--strict` fails on
  warnings; `pure compile --check` remains the artifact freshness check.
- Fluent component calls: `component('Card', ...$children)` returns a
  `Pure\Component\Call` whose props are set like tag attributes
  (`->type('Free')->class('btn btn-lg')`), whose children bind the reserved
  `children` slot (`Slot::raw('children')`), and whose `null` prop leaves the
  prop unset. `class()` and `style()` join their arguments exactly like the tag
  setters, an unknown prop is reported by the development guard with a
  `did you mean`, and children on a template without a `children` slot throw.
  A call function may type its props itself and return a `Call`
  (`function Badge(string $label): Call`), so the call site is checked by PHP.
  The recommended form keeps the prop contract in `prepare()`; the docs use
  it throughout.
- `Pure\Core\Markup`: trusted markup emitted verbatim in child position.
  `Raw` implements it, and so does `Call`, so `div(Card(...))` nests like a tag
  and renders lazily with the tree; every other child is still frozen to text
  and escaped. A `Markup` child inside a data-free shape is a compile error
  (`CompileException::markupInShape`), because baking a component call into an
  artifact would freeze its runtime data.
- `register(..., prepare: ...)` gives a fluent unit typed props: the closure's
  parameters are the prop contract (PHP enforces the types, missing and unknown
  props fail before rendering) and the array it returns binds the template.
  `pure check` compares the `prepare()` parameters and returned keys against the
  slots, and the fluent calls in every checked file against the props their
  target accepts.
- `Call::props(array $props)` sets several props at once from a record whose
  keys are prop names (`IconColumn()->props($record)`), which keeps
  record-to-component mapping short inside `prepare()` closures.
- The bootstrap, event-counter and xml examples call their components fluently:
  the pricing deck builds `Card()->type('Free')->price('0')->features([...])`,
  the features page passes record mappers to `Section()->...->item(...)`, and
  the units keep their logic in `prepare()` closures (`Section`, `CardDeck`,
  `PageHeader`, `PageFooter`, `FeatureSection`, `PricingHeader`, `CustomCard`,
  `IconColumn`, `CellIcon`, `HangingIcon`, `FeatureTitle`). The rendered output
  is byte-identical to the function-call version, and the example's `plain()`
  loader renders Markup bindings to strings before the plain view loads, so that
  view still needs nothing of purephp at render time.
- `pure check` understands the migrated units: a fluent unit without
  `prepare()` is reported as checked through its call sites instead of "no
  render() call targets this component", and the returned-keys reader now
  handles interpolated strings, so a `prepare()` literal like
  `"background-image: url('{$bg}');"` no longer hides its keys.
- `Pure\Component\Prop` declares what a `prepare()` signature cannot, and
  `pure check` verifies it against the template: `slot` names the binding the
  prop fills (the parameter name by default), `item` the single slot each item
  of a list prop fills in the item shape of a `Slot::each` slot, `required` the
  caller obligation (a declaration contradicting the signature is reported) and
  `deprecated` a migration hint that every call site binding the prop is warned
  about. When `prepare()` does not return one readable array literal, the
  declared slots are what the required slots are checked against, so a computed
  `prepare()` no longer goes unchecked; `Card` and `CustomCard` in the
  bootstrap example declare their list items and their renamed `style` prop.
  The attribute is read by `pure check` only and never takes part in rendering.
- `Pure\Component\Trusted` marks a prepare() prop that carries markup:
  `pure check` verifies it binds a raw slot (markup bound to a text slot would
  be escaped, and a slot read as both raw and text is reported), and the
  development guard warns when a call passes a value that is not
  `Pure\Core\Markup` — the place where untrusted input reaches the output. A
  `#[Trusted]` parameter typed as a scalar is reported too, because it can never
  receive markup.
- `Pure\Component\Binds` declares the keys a prepare() hook or a
  `...bindings()` helper returns, so a hook that builds its bindings in steps or
  merges them from a service is still compared with the template: the declared
  keys cover the required slots, a declared key the template does not read is an
  error, and a declared key a readable literal does not return is one too. The
  bootstrap example's `PricingHeader` declares `#[Binds('title', 'desc')]` and
  no longer reports its bindings as uncompared.
- A `#[Prop(deprecated: ...)]` prop now warns in the development guard as well
  (once per component and prop, only while the guard is on), so the hint reaches
  a developer who never runs `pure check`. The guard reads the declarations once
  per component and compile generation, so a production call pays one boolean.
- `pure check` compares the item keys of a list prop bound to an array literal
  at the call site with the item shape of its slot, so
  `->links([['txet' => 'Team']])` is reported where it is written, with a
  `did you mean` suggestion and a missing-required-key error. Multi-slot item
  shapes therefore need no declaration of their own: the nested shape is the
  contract.
- `Pure\Utils\renderHTML()` / `renderXML()` render a tag tree or a component
  call and prepend the document header (`<!DOCTYPE html>` / the XML
  declaration), so a page no longer concatenates the header by hand. The
  headers stay available as `HTML::DOCUMENT_HEADER` / `XML::DOCUMENT_HEADER`,
  which the tag classes and the new functions share.

### Changed

- **Breaking** — `register()` is narrowed to
  `register(Closure $call, ?Closure $factory = null, bool $override = false,
  ?Closure $prepare = null)`: the first argument must be the call function as
  `Icon(...)`, and the component name and unit file derive from it. The classic
  `register('Icon', __FILE__, ...)` form therefore fails with a native PHP
  `TypeError` — not a library `InvalidArgumentException` — because the first
  parameter is typed `Closure`. The parameters were renamed accordingly
  (`$name` → `$call`, `$file` removed), so named arguments `name:` and
  `file:` also fail; `factory:`, `prepare:` and `override:` are unchanged.
  Migration — pass the call function: `register(Icon(...), $factory)`, with
  the factory optionally in `prepare:` / named `factory:` position.
- `Slot::value($name)` replaces `Slot::text()` and `Slot::attr()`: the slot name
  is the data key and its position decides the semantics (child position
  escapes to text; attribute position follows `Tag::setAttr()` with bool/null
  omission). Generated code is byte-identical for equivalent usages; the
  fingerprint encodes the slot kind name, so it keys differently and
  `Compile::CACHE_VERSION` is bumped 9 → 12 (the slot-kind rekeying, the
  `Slot::eachKind()` removal below and the plain raw-iterable join). Local
  `*.pure.php` / `*.plain.php` artifacts become stale and must be regenerated
  with `pure compile --plain` (plain views are not rebuilt or checked without
  `--plain`); they are gitignored and not committed.
- `register()` factories and `*.shape.php` templates may now return a bare tag
  tree; `Registry` and `ArtifactCompiler` wrap it in `Compile::shape()`
  automatically. `Compile::shape()` stays as the explicit API.
- `render()` returns `string` instead of `Raw`. Component function signatures
  change from `: Raw` to `: string` and the `Raw::of()` wrapper is dropped.
  `Raw` remains available in `Pure\Core` for verbatim children inside a raw
  slot.

- `Slot::child()`, `Slot::each()` and `Slot::if()` accept a
  bare tag tree: `Tag` implements `ShapeContract` by returning itself, so
  `Slot::each('items', li(Slot::value('value')))` no longer needs a
  `Compile::shape()` wrapper (which is still accepted, and still the way to
  build and memoize a nested tree separately). The examples, tests and guides
  use the bare form, with one test keeping the wrapped form covered.
- A `Slot::raw()` value may be an iterable of stringable values, not only a
  single one: `SlotRuntime::raw()` stringifies each element and concatenates
  them, so a rendered list of component markup goes straight into the slot
  without an `implode()`. Value and raw slots accept a `Raw` (or any
  `Stringable`) as it is, so a component result is passed to its parent without
  a `(string)` cast; the examples and guides drop theirs. Nested arrays still
  raise an `InvalidArgumentException` naming the slot path.

- `pure compile` skips the files whose content is already current: the shape is
  still loaded and compiled (so a change in anything it pulls in is picked up),
  but the write, the load-back verification and the rename are skipped and the
  file is reported as `unchanged:` instead of `compiled:`.
- Artifact slots read through merged fast paths in `TemplateRuntime`: a present,
  non-null value skips the requiredness check and the default, scalar text and
  attributes escape inline with the shared `Escaper` flags, and attributes build
  their ` name="value"` chunk without the `SlotRuntime` / `Escaper` call chain.
  `func_num_args()` and the compiled default are only consulted when the value
  is missing or null, and the accessor signatures are unchanged, so existing
  templates keep rendering; a component template renders about 2.2x faster
  (3.9 us -> 1.7 us in the microbenchmark) and stays byte-identical.
- `ShapeIndex` encodes a null slot default (every required slot) without
  `serialize()`, cutting about a tenth of the fingerprint walk;
  `Compile::CACHE_VERSION` is bumped for the new fingerprints. A bump discards
  the `Compile::cachePath()` renderers automatically, but it does not touch the
  `*.pure.php` artifacts beside your templates: run `pure compile` after every
  upgrade (see the artifact guard below).

- The examples are function components: each component is a function with typed
  parameters returning `string`, backed by a fixed `*.shape.php` template, and
  pages are functions too (`featuresPage()`, `pricingPage()`, `counterPage()`,
  `xmlPage()`, `coverPage()`). Child components are called by their parent and
  injected through `Slot::raw()`; controllers call the page functions, the
  example `view()` helper is gone, and the plain controllers pass the same
  bindings to the dependency-free view file.
- `Tag::toPrint()` / `Tag::toSave()` are now `Tag::print()` / `Tag::save()`, so
  one verb names one action everywhere: `render()` returns a string, `print()`
  echoes, `save()` writes a file, and `toJSON()` / `$source` expose the
  structure for debugging.
- `HTML`, `SVG`, `XML`, `Shape` and `Renderer` constructors are `@internal`:
  tags are created with the functions or the magic static surface
  (`HTML::customTag()`, `XML::customer()`), shapes with `Compile::shape()`; the
  docs no longer present constructors as a user-facing alternative.
- `Slot::value($name)` treats `$name` as the data key everywhere: compile errors
  and missing-slot exceptions now report the slot name instead of the attribute
  name, so `->class(Slot::value('classList'))` reports `classList`.
- Generated sources are memoized per fingerprint in memory (on top of the
  on-disk cache), so a tree rebuilt in the same process is re-evaluated instead
  of regenerated. The memo is bounded by a byte budget (oldest sources are dropped
  first), so a structure that varies per request cannot grow it without limit;
  `PURE_COMPILE_MEMO_BYTES` changes the budget and `0` disables the memo, and
  `Compile::flush()` clears it.
- The concepts guide states the one-line rule (data drives the output → slots
  and shapes; snippets and debugging → immediate rendering) and the verb table,
  and the quick start documents the development guard.
- `Renderer::__invoke()` is now the named `Renderer::render($data)`, the
  `Renderer::print()` alias was removed, and `Renderer::$source` / `Renderer::$id`
  are public readonly properties instead of `source()` / `id()` getters, so the
  invokable and echo surfaces stay on the user-facing `Shape`;
  `Shape::__invoke()` and `Shape::print()` delegate to `Renderer::render()`.
- `Tag::toJSON()` now returns a nested structure (`tagName`, `attrs`, `children`)
  so an attribute can no longer overwrite the structural keys.
- `Tag::getAttr()` returns `null` for a missing attribute instead of emitting a
  warning and failing on the return type; `Tag::setAttrByCb()` passes `null` to
  the callback for a missing attribute.
- `Tag::save()` moved to the base class as
  `save(string $path, ?string $header = null)`; `HTML`, `XML` and `SVG`
  provide their default document headers via `defaultHeader()`. Subclasses that
  override `save()` must accept the new optional `$header` parameter (or drop
  the override).
- `Slot::if()` now rejects `required()` and `default()` with a
  `LogicException` instead of silently ignoring them.
- `Raw::$content` is now the public readonly `Raw::$value`, the constructor is
  private (create instances with `Raw::of()`), and `Tag::toJSON()` serializes
  Raw children as their value string.
- `clx()` accepts and drops booleans (`->class('btn', $cond && 'active')` keeps
  working), accepts numbers in scalar and array position, keeps explicit
  non-empty strings including `"0"`, and no longer exports a
  `filterClassList()` helper.
- Exceptions unified: `BadMethodCallException` for attribute arity,
  `InvalidArgumentException` for invalid attribute names, `LogicException` for
  self-closing conflicts; compile-time violations use the new
  `Pure\Compile\CompileException` (extends `LogicException`).
- Internals: `RendererCache` and `ShapeGuard` extracted from `Compile`;
  `SlotRuntime` replaces `Values` and generated conditions use a plain bool
  cast; `ShapeIndex` is a value object; escaping flags/encoding stay shared
  through `Pure\Core\Escaper` and the attribute chunk format lives in
  `Escaper::attribute()`, while slot text/attribute values inline the same
  `htmlspecialchars` call for speed (byte-identity locked by a cross-path
  test). A single-consumer visitor adapter and single-use exception factories
  were dropped to keep the abstraction surface minimal.
  `Compile::CACHE_VERSION` was bumped for the new fingerprints, so cached
  renderers written by earlier versions are discarded and regenerated.
- `SELF_CLOSE_HTML_TAGS` and `SELF_CLOSE_SVG_TAGS` are keyed sets
  (`['br' => true]`) instead of lists, so membership is an `isset()` lookup
  rather than a linear `in_array()` scan; constructing a tag is ~10% faster
  (shape building happens once per process, so this is a compile-time win).
- SVG self-closing now applies only to elements created without children: SVG
  has no void elements, so the short form is a style choice and children must
  win. Animation elements can nest `<mpath>`
  (`animateMotion(mpath()->href('#p'))`) and `<use>` can nest descriptive
  elements instead of throwing a `LogicException`, while the list gains the
  remaining leaf elements (`animateTransform`, `set`, `view`, `feOffset`,
  `feTile`, `feFlood`, `feTurbulence`, `feComposite`, `feConvolveMatrix`,
  `feMorphology`, `feMergeNode`, `feFuncA`, `feFuncB`, `feFuncG`, `feFuncR`,
  `feDistantLight`, `fePointLight`, `feSpotLight`) so they render compactly.
  Containers stay out of the list. HTML void elements still reject children,
  and the self-close flag is part of the structure fingerprint, so cached
  renderers for the affected elements are regenerated without a
  `Compile::CACHE_VERSION` bump.
- Renamed the compiled renderer `Pure\Compile\Compiled` to
  `Pure\Compile\Renderer`; the internal code generator is now
  `Pure\Compile\Internal\CodeGenerator` (previously `Compiler`).
- Shape-tree traversal now lives in a single
  `Pure\Compile\Internal\ShapeWalker` consumed by both the structure fingerprint
  and the code generator, so paths and ordering cannot drift apart.
- `Pure\Core\Slot` types shape arguments against the new
  `Pure\Core\ShapeContract` (implemented by `Pure\Compile\Shape`), so
  `Pure\Core` no longer depends on `Pure\Compile`.
- Slot values are allowed as tag children and attribute values; `Tag::render()`,
  `print()` and `save()` throw a `LogicException` for trees containing
  slots, and `toJSON()` describes slots as `['slot' => '<name>']`.
- Every example renders through the compiled path; the classic component
  implementations moved to `bench/fixtures/` as benchmark baselines.
- String children are now escaped instead of tag-filtered: `div('<p>x</p>')`
  renders `&lt;p&gt;x&lt;/p&gt;` and `2<3` / `a<b` are no longer silently
  dropped. Static and bound text behave identically; `Raw::of()` remains the
  explicit way to emit trusted markup.
- `SlotRuntime` value coercion takes an inline fast path for scalars and null,
  and `Slot::attr()` handles booleans like `Tag::setAttr()`; compiled rendering
  is ~12% faster on the 600-element benchmark page (139–144 µs → 124–125 µs).
- Generated renderers escape scalar text slots inline with the shared
  `Escaper::FLAGS` / `Escaper::ENCODING` constants and keep
  `SlotRuntime::text()` as the fallback for null, `Stringable` and invalid
  values, so the documented empty-null and `InvalidArgumentException`
  behaviour is unchanged; compiled rendering is another ~5% faster
  (124 µs → 118 µs with opcache). `Compile::CACHE_VERSION` is now 5 because
  the generated code and the layout both changed.
- `Tag::save()` and `Renderer::save()` write with `file_put_contents()`, so a
  long document is always written in full.
- `Slot::sub()` and `Slot::eachAny()` are now `Slot::child()` and
  `Slot::each()` (with `SlotKind::Child` / `SlotKind::Each`), matching the
  vocabulary the guides already used (child component, list); the
  internal scope helper is `SlotRuntime::scope()`, so `Compile::CACHE_VERSION` is
  now 6 and cached renderers are discarded and regenerated.
- Source directories now mirror the namespaces one to one —
  `src/Core`, `src/Compile`, `src/HTML`, `src/SVG`, `src/Utils` — and the
  function frontends live in `src/HTML/functions.php` and
  `src/SVG/functions.php`; the PSR-4 section collapses to a single
  `"Pure\\": "src/"` rule. The `@internal` machinery moved to
  `Pure\Compile\Internal\*` so the frozen surface (`Compile`, `Shape`,
  `Renderer`, `CompileException`) is visible in the tree. Generated renderers
  reference   `Pure\Compile\Internal\SlotRuntime`, so caches written by earlier
  versions are discarded and rebuilt.
- **Breaking** — a required `Slot::value()` / `Slot::raw()` slot rejects an
  explicit `null` with `slot 'x' is required but was null.` instead of silently
  rendering empty; attribute slots keep omitting themselves for `null`, and
  `->required(false)` / `->default(null)` keep the empty rendering, so only
  templates that passed a `null` where a value was required are affected.
- A plain view takes the document header only from a document root: pages
  (`<html>` and XML roots) keep their `<!DOCTYPE html>` / XML declaration, while
  a fragment view (`Card.plain.php`, an SVG icon) starts with its markup, so
  including one cannot inject a header into the middle of a document.
  `Compile::CACHE_VERSION` is now 13 because artifacts embed the slot manifest;
  regenerate local artifacts with `pure compile --plain`.

### Removed

- **Breaking** — The `Pure\Component\Component` function attribute and its
  PHPStan collector `Pure\StaticAnalysis\ComponentAttributeCollector`. Code
  referencing either class fails with a class-not-found error, and a custom
  `phpstan.neon` that still registers the collector fails at startup. The call
  function is found by its short name matching the registered name (a
  `#[Template]` builder of the same name is skipped), so `pure check` no
  longer reports a pin-name mismatch (`#[Component('X')]`) or a second mark in
  one unit file; `#[Template]`, `#[Prop]`, `#[Trusted]` and `#[Binds]` are
  unaffected.
- **Breaking** — The classic component form (a typed function returning the
  rendered `string` through `Pure\Component\render()`) is removed, together with
  the function itself. A unit is called fluently: its call function returns
  `Pure\Component\Call` (`function Card(mixed ...$children): Call`) and its typed
  prop contract is the `prepare()` hook of `register()`. A page unit owns its
  bindings through that hook, `#[Binds]` declares the keys of a hook that returns
  a helper's array instead of a literal, and a unit without `prepare()` is
  checked at its call sites; a same-named function that returns neither `Call`
  nor comes with a hook is reported by `pure check` as an error. The docs and the
  examples use the fluent form throughout.
- **Breaking** — `Pure\Component\bind()` is removed. Inline trees bind through
  `Compile::shape($tree)` (invokable) or `Compile::shape($tree)->compile()->render($data)`;
  file-backed templates bind through `Registry::component($nameOrPath)`, which
  returns a `Closure(array): string`.
- **Breaking** — `Slot::text()` and `Slot::attr()` are removed in favour of
  `Slot::value()`.
- **Breaking** — `Slot::eachKind()` and `SlotKind::EachKind` are removed. A list
  whose items need different markup is dispatched in the data layer: render each
  item through the component function that fits it and pass the joined markup
  into a raw slot, while `Slot::each()` covers the homogeneous case. Cached
  renderers and artifacts written with the old kind are discarded by the
  `Compile::CACHE_VERSION` bump.
- **Breaking** — The `$map` third argument of `Slot::child()` and
  `Slot::each()`, along with the closure-copying machinery that carried maps
  into artifacts (`Pure\Compile\Internal\ClosureSource`, namespace blocks and
  imported-name splitting in generated files). A nested scope now always reads
  `$data[$name]`, and bindings carry the nested array the shape expects, so the
  adaptation lives in the data layer where it is explicit and testable. The
  `$maps` parameter of the `Renderer` constructor and the `maps=` field of cache
  and artifact headers are gone with it.
  Migration — derive the nested data where the data is built:

  ```php
  // before: the shape reached into the parent scope through a map
  Slot::each('rows', RowShape(), static fn (array $d): array => ['href' => '#' . $d['icon']]);

  // after: the caller binds the array the child shape reads
  Slot::each('rows', Compile::shape(li(Slot::text('href'))));
  // … and the data: ['rows' => array_map(static fn (array $r): array => ['href' => '#' . $r['icon']], $rows)]
  ```

  Because the constructor signature changed, an artifact written before the
  removal fatals when a newer `Renderer` loads it; the cache-version guard above
  turns that into a `pure compile` message, and `--check` reports the file.
- `Pure\Core\Dom`, `PDom` and `NDom`, and `Tag::toDom()` in favor of string
  rendering and the compiled path.
- `Pure\Core\RawType`, `Raw::toJSON()`, and the `Pure\Utils\rawHtml()` /
  `rawXml()` / `raw()` helpers; trusted markup is created with
  `Pure\Core\Raw::of($value)`.
- **Breaking** — `Tag::setAttrByCb()` is removed. Attributes are set once while
  the tree is built (`->class(...)`, `setAttrs()`; a `null` value skips the
  attribute) and dynamic values bind through slots, so a callback that rewrites
  or removes an attribute has no place in the build-once workflow.

### Fixed

- A plain view joins an iterable raw slot with `implode()` instead of echoing
  the array, so a `Slot::raw()` list of stringables renders in both flavors
  (a `Traversable` is materialized first). The plain `@var` annotation of a raw
  slot now includes the iterable form.

- The structure fingerprint left out the attribute name of a slot-valued
  attribute, because the slot path it encodes carries the slot name only:
  `->class(Slot::attr('x'))` and `->id(Slot::attr('x'))` shared an `id()`, so
  with `Compile::cachePath()` enabled the second shape was served the first
  one's cached renderer and printed the wrong attribute. The attribute name is
  now part of the fingerprint and `Compile::CACHE_VERSION` is 8.
- `Pure\Component\component()` with the path of a `*.cmp.php` unit that has no
  artifact required the unit and then reported that the file "must return a
  Shape", which is what a `*.shape.php` template must do. It now says the file is
  a component unit and how to render it, and an unregistered unit path is no
  longer loaded as a template at all.
- SVG camelCase self-closing tags (`animateMotion`, `feBlend`, `feColorMatrix`,
  `feDisplacementMap`, `feDropShadow`, `feGaussianBlur`, `feImage`) are matched
  case-sensitively again, as the API documentation always claimed.
- Bound boolean attribute values via `Slot::attr()` now match static attributes:
  `false` omits the attribute and `true` renders its name as value instead of
  `disabled=""` / `disabled="1"`.
- The structure fingerprint is rebuilt from the live tree on every compile, so a
  shape tree mutated after `id()` or a first compile can no longer generate code
  under a stale id or poison the on-disk cache for an identically shaped tree.
  `Shape::id()` reflects mutations immediately; an already compiled renderer
  still describes the tree state it was compiled from until `Compile::flush()`.
- `Tag::setAttrs()` / `setAttrByCb()` / `getAttr()` normalize `className` and
  underscored keys like the chained setters, and non-stringable attribute
  values raise an `InvalidArgumentException` instead of an "Array to string
  conversion" warning and a literal `"Array"` attribute.
- `class('')` no longer emits `class=""`; it behaves like `class(null)` and
  `class([''])`.
- `->default()` rejects objects, closures and resources with an
  `InvalidArgumentException` instead of failing later with a bare
  `serialize()` error or broken generated code.
- The plain view of a fragment root carried the document header of its
  vocabulary class, so a compiled `Card.plain.php` started with
  `<!DOCTYPE html>` (and an SVG icon view with the XML declaration) while its
  artifact rendered bare markup, contradicting the byte-identity claim. Plain
  views now follow `Tag::isDocumentRoot()`.

[Unreleased]: https://github.com/YonLD/purephp/commits/main
[1.1.0]: https://github.com/YonLD/purephp/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/YonLD/purephp/releases/tag/v1.0.0
