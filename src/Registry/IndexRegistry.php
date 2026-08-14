<?php
namespace CGM\Core\Registry;

/**
 * Search-index definitions. A plugin (e.g. Typesense, SEO, native search) registers
 * an index describing which content types and fields it owns; Core fans content and
 * relationship changes out to `index.rebuild` events for those indexes.
 */
final class IndexRegistry extends AbstractRegistry {}
