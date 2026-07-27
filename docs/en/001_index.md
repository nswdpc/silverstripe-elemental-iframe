# Documentation

## Polyfill

All major browsers support iframe lazy loading [via the `loading` attribute](https://caniuse.com/loading-lazy-attr).

To enable the javascript polyfill, set the `load_polyfill` configuration option on ElementIframe to true in your project configuration.

```yml
NSWDPC\Elemental\Models\Iframe\ElementIframe:
  load_polyfill: true
```

Enabling the setting will add the loading-attribute-polyfill script at https://cdnjs.cloudflare.com to a page with the iframe element.
