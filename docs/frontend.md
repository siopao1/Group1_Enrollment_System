# Frontend

Plain ES modules. No framework, no bundler, no build step — the browser loads
`main.js` as `type="module"` and follows the imports.

## Layers

```
pages/        render a screen and handle its events
  ↓
services/     use-cases, client-side validation, derived values
  ↓
api/          one module per REST resource; knows endpoints only
  ↓
core/apiClient.js   the only place that calls fetch()
  ↓
mock/         TEMPORARY stand-in transport, swapped in by main.js
```

A page never builds a URL. A service never touches the DOM. That separation is
why connecting the real backend is a one-line change.

## core/

| Module | Responsibility |
|---|---|
| `apiClient.js` | Base URL, headers, envelope unwrapping, `ApiError` |
| `auth.js` | Client session state, `can()`, change notifications |
| `router.js` | Hash routes, lazy page imports, guards, loading/error states |
| `storage.js` | Namespaced localStorage/sessionStorage with JSON and failure handling |
| `validator.js` | Shared rules, mirroring `App\Core\Validator` |
| `dom.js` | `qs`, `qsa`, `on`, `delegate`, `escapeHtml`, `formValues` |
| `apiError.js` | One error type with `isUnauthorized`, `isValidation`, … |

## Page contract

```js
export default {
  async render(ctx) { return "<html string>"; },  // may await data
  mount(ctx) { return () => {/* cleanup */}; },   // bind events; return teardown
};
```

`ctx` carries `params`, `route` and `navigate`. The router shows a loading state
while `render()` awaits, an error state with a retry button if it throws, and
calls the teardown before the next page renders — so listeners and timers cannot
leak between screens.

## Adding a page

1. Create `pages/myThing.js` exporting `render` and `mount`.
2. Add an entry to `routes.js` with `path`, `title`, `breadcrumb`, `permission`
   and a `load` that dynamically imports the module.
3. If it needs a nav item, add one to `config/navigation.js` with the same
   permission — it appears only for roles that hold it.
4. Add a service function if it needs data; add an api function if it needs a
   new endpoint. Do not call `fetch` from the page.

## Removing the mock layer

1. `useMockApi: false` in `config/app.config.js`
2. Delete `public/assets/js/mock/`
3. Delete the `import("./mock/mockServer.js")` block in `main.js` and `login.js`

Nothing else changes: `api/`, `services/`, `components/` and `pages/` never knew
the mock existed. The mock answers the same paths, the same envelope and the
same status codes the PHP API does, so behaviour including error handling is
already exercised.

## Events

The old code re-queried and re-bound every control after each render, which
stacked listeners. Pages now use `delegate(root, selector, type, handler)`: one
listener on a container that survives re-rendering its contents. Table bodies
re-render on filter changes without touching the toolbar's listeners.

## Escaping

Every value interpolated into an HTML string goes through `escapeHtml()`. It
matters even for "our own" data: a student named `O'Brien <test>` would
otherwise break the markup, and a hostile value could inject script.

## CSS

`app.css` imports the layers in cascade order:

```
base/        variables (design tokens), reset
layout/      shell, topnav, topbar, content, responsive (last)
components/  buttons, cards, tables, forms, modal, toast, badges, …
pages/       profile, enrollment, reports, auth
```

The split is mechanical: the original single stylesheet was divided along its
own section comments, and the concatenation was verified to be identical to the
original. The design is unchanged. `layout/responsive.css` must stay last,
because its media queries override earlier rules.

When adding styles, prefer an existing component file over a page file, and use
the tokens in `base/variables.css` rather than new literal colours.
