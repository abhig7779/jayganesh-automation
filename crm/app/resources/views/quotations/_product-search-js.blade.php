{{--
    Item search for the quotation screens. The matcher now lives in resources/js/product-search.js
    (shared with billing) and app.js exposes it as window.jgProductSearch and window.jgRupees:
      jgProductSearch.load(url)          → Promise (fetches the products index once; ETag revalidates)
      jgProductSearch.search(q, limit)   → { qty, exact, results: [product…] }
      jgProductSearch.popular(limit)     → top products by sold_count
    Every word as a prefix (aliases included), exact SKU first, one typo for 4+ letter words,
    "2*gleam" adds two. Product prices from it are GST-inclusive (price_paise).
    Kept as an include so the screens that @include it need no change.
--}}
